<?php

namespace App\Console\Commands;

use App\Models\ClientBalanceLog;
use App\Models\HostingAccount;
use App\Services\Billing\HourlyRateCalculator;
use App\Services\Hosting\HostingPanelFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Potong saldo klien untuk layanan yang ditagih PER JAM (billing_mode
 * = 'deposit'), bukan lewat invoice bulanan. Sengaja generik -- tidak
 * peduli layanan itu VM/VPS atau jenis lain, selama hosting_account-nya
 * punya billing_mode='deposit' dan hourly_rate terisi, command ini
 * akan menagihnya.
 *
 * Dijadwalkan jalan tiap jam (lihat routes/console.php atau
 * app/Console/Kernel.php), tapi menghitung durasi SUNGGUHAN sejak
 * potongan terakhir (last_billed_at) -- bukan asumsi "pasti 1 jam
 * pas" -- supaya tetap akurat meski command sempat telat/terlewat
 * jalan sekali dua kali.
 */
class ChargeHourlyUsage extends Command
{
    protected $signature = 'lumora:charge-hourly-usage {--dry : Tampilkan yang AKAN terjadi tanpa benar-benar memotong saldo}';

    protected $description = 'Potong saldo klien untuk layanan deposit (per jam) yang sedang aktif berjalan';

    public function handle(): int
    {
        $dry = $this->option('dry');

        $accounts = HostingAccount::where('billing_mode', 'deposit')
            ->where('status', 'active')
            ->with(['client', 'serverModel'])
            ->get()
            ->filter(fn ($a) => $this->effectiveRate($a) > 0);

        if ($accounts->isEmpty()) {
            $this->info('Tidak ada layanan deposit yang aktif saat ini.');

            // Jelaskan PENYEBABNYA -- tanpa ini, "kosong" bisa berarti
            // banyak hal berbeda (status belum aktif, mode salah, tarif
            // nol) yang masing-masing perbaikannya beda.
            $semuaDeposit = HostingAccount::where('billing_mode', 'deposit')->with('serverModel')->get();

            if ($semuaDeposit->isEmpty()) {
                $this->line('  Tidak ada layanan bermode "deposit" sama sekali.');
                $this->line('  → Cek kolom "Mode Tagihan" di layanan yang bersangkutan.');

                return self::SUCCESS;
            }

            $this->newLine();
            $this->line("Ada {$semuaDeposit->count()} layanan bermode deposit, tapi tidak ada yang memenuhi syarat:");

            foreach ($semuaDeposit as $a) {
                $rate = $this->effectiveRate($a);
                $alasan = [];

                if ($a->status !== 'active') {
                    $alasan[] = "status \"{$a->status}\" (harus \"active\")";
                }

                if ($rate <= 0) {
                    $alasan[] = $a->hasVmSpec()
                        ? 'tarif 0 — kartu harga server kosong atau markup belum diatur'
                        : 'spesifikasi VM tidak terbaca dari kolom package';
                }

                $this->line("  #{$a->id} {$a->domain} — " . (empty($alasan) ? 'OK' : implode(', ', $alasan)));
            }

            $this->newLine();
            $this->line('Layanan hanya ditagih kalau: mode=deposit, status=active, dan tarif > 0.');

            return self::SUCCESS;
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . "Memeriksa {$accounts->count()} layanan deposit aktif...");

        $charged = 0;
        $suspended = 0;

        foreach ($accounts as $account) {
            $client = $account->client;

            if (! $client) {
                continue;
            }

            // Sejak kapan mulai dihitung -- kalau belum pernah ditagih
            // sama sekali, dihitung sejak layanan dibuat (created_at),
            // bukan dari waktu sekarang (supaya jam pertama tetap tertagih).
            $since = $account->last_billed_at ?? $account->created_at;
            $hours = $since->diffInSeconds(now()) / 3600;

            if ($hours <= 0) {
                continue;
            }

            $rate = $this->effectiveRate($account);
            $charge = round($rate * $hours, 2);
            $balance = (float) $client->balance;

            $this->line(sprintf(
                '  #%d %s — %.3f jam x Rp%s = Rp%s (saldo: Rp%s)',
                $account->id, $account->domain, $hours,
                number_format($rate, 4), number_format($charge, 2), number_format($balance, 2)
            ));

            if ($dry) {
                continue;
            }

            try {
                DB::transaction(function () use ($account, $client, $charge, $balance, &$charged, &$suspended) {
                    if ($balance >= $charge) {
                        // Saldo cukup -- potong penuh, layanan tetap jalan.
                        $this->applyCharge($client, $account, $charge, "Pemakaian {$account->domain}");
                        $account->update(['last_billed_at' => now()]);
                        $charged++;

                        return;
                    }

                    // Saldo TIDAK cukup untuk tagihan penuh -- ambil
                    // sisa saldo yang ada (sampai habis, tidak sampai
                    // minus), lalu suspend layanannya. Klien tetap kena
                    // tagih untuk waktu yang SUDAH terpakai, bukan
                    // dibebaskan begitu saja.
                    if ($balance > 0) {
                        $this->applyCharge($client, $account, $balance, "Pemakaian {$account->domain} (saldo habis di tengah siklus)");
                    }

                    $account->update(['last_billed_at' => now(), 'status' => 'suspended']);
                    $suspended++;

                    $this->suspendProvisioned($account);
                });
            } catch (Throwable $e) {
                Log::error("Gagal memproses tagihan jam untuk hosting_account #{$account->id}: " . $e->getMessage());
                $this->error("  !! Gagal: {$e->getMessage()}");
            }
        }

        if (! $dry) {
            $this->info("Selesai. {$charged} layanan ditagih, {$suspended} disuspend karena saldo habis.");
        }

        return self::SUCCESS;
    }

    /**
     * Tarif per jam SUNGGUHAN yang dipakai untuk layanan ini.
     *
     * Jalur utama: dihitung dari kartu harga server (per komponen:
     * CPU/RAM/storage/backup/snapshot/lisensi Windows) x spesifikasi
     * VM (tersimpan di panel_package sebagai JSON) -- lihat
     * HourlyRateCalculator. Ini yang dipakai untuk VM/VPS sungguhan.
     *
     * Jalur cadangan: kalau server tidak (belum) punya kartu harga,
     * atau layanannya bukan VM (tidak ada panel_package berformat
     * spek), dipakai angka hourly_rate yang diisi manual di form --
     * berguna untuk uji coba atau layanan non-VM yang tetap mau
     * ditagih per jam dengan tarif flat.
     */
    private function effectiveRate(HostingAccount $account): float
    {
        if ($account->serverModel && $account->hasVmSpec()) {
            $rate = HourlyRateCalculator::calculate($account->serverModel, $account->vmSpec());

            if ($rate > 0) {
                return $rate;
            }
        }

        return (float) ($account->hourly_rate ?? 0);
    }

    private function applyCharge($client, HostingAccount $account, float $amount, string $description): void
    {
        $client->decrement('balance', $amount);
        $client->refresh();

        ClientBalanceLog::create([
            'client_id'     => $client->id,
            'amount'        => -$amount,
            'type'          => 'usage_charge',
            'description'   => $description,
            'balance_after' => $client->balance,
        ]);
    }

    /**
     * Suspend SUNGGUHAN di panel (WHM/IDCloudHost/dst) -- bukan cuma
     * ubah status di database. Dibungkus try-catch terpisah supaya
     * kegagalan panggilan API tidak membatalkan pemotongan saldo yang
     * sudah terjadi (saldo tetap harus tercatat berkurang, terlepas
     * dari berhasil-tidaknya panel merespons).
     */
    private function suspendProvisioned(HostingAccount $account): void
    {
        if (! $account->server_id || ! $account->username) {
            return;
        }

        try {
            $service = HostingPanelFactory::make($account->serverModel);
            $service->suspendAccount($account->username, 'Saldo deposit habis');
        } catch (Throwable $e) {
            Log::warning("Layanan #{$account->id} ditandai suspended di database, tapi panggilan suspend ke panel gagal: " . $e->getMessage());
        }
    }
}
