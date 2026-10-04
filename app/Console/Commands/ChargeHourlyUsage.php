<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\HostingAccount;
use App\Services\Billing\HourlyRateCalculator;
use App\Services\Billing\CreditService;
use App\Services\Hosting\HostingPanelFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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
    public function __construct(private readonly CreditService $credits) { parent::__construct(); }
    protected $signature = 'lumora:charge-hourly-usage {--dry : Tampilkan yang AKAN terjadi tanpa benar-benar memotong saldo}';

    protected $description = 'Potong saldo klien untuk layanan deposit (per jam) yang sedang aktif berjalan';

    public function handle(): int
    {
        return $this->handleJob();
    }

    private function handleJob(): int
    {
        $dry = $this->option('dry');

        $accounts = HostingAccount::where('billing_mode', 'deposit')
            ->where('status', 'active')
            ->with(['client', 'serverModel'])
            ->get()
            ->filter(fn ($a) => filled($a->panel_suspend_error) || $this->effectiveRate($a) > 0);

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
        $failed = 0;

        foreach ($accounts as $account) {
            $client = $account->client;

            if (! $client) {
                $failed++;
                $this->error("  !! Layanan #{$account->id} tidak memiliki klien yang dapat ditagih.");
                continue;
            }

            // Sejak kapan mulai dihitung -- kalau belum pernah ditagih
            // sama sekali, dihitung sejak layanan dibuat (created_at),
            // bukan dari waktu sekarang (supaya jam pertama tetap tertagih).
            $since = $account->last_billed_at ?? $account->created_at;
            $hours = $since->diffInSeconds(now()) / 3600;

            if ($hours <= 0) {
                // Kegagalan suspend sebelumnya tidak boleh hilang hanya
                // karena cron dijalankan ulang sebelum satu jam berlalu.
                if ($account->panel_suspend_error && ! $dry) {
                    try {
                        $this->suspendProvisioned($account);
                        $account->update([
                            'status' => 'suspended',
                            'panel_suspend_error' => null,
                        ]);
                        $suspended++;
                    } catch (Throwable $e) {
                        $failed++;
                        $account->update(['panel_suspend_error' => mb_substr($e->getMessage(), 0, 4000)]);
                        Log::error("Gagal mencoba ulang suspend panel untuk hosting_account #{$account->id}: " . $e->getMessage());
                        $this->error("  !! Gagal mencoba ulang suspend: {$e->getMessage()}");
                    }
                } elseif ($account->panel_suspend_error) {
                    $this->line("  [DRY RUN] #{$account->id} menunggu percobaan ulang suspend panel.");
                }

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

            $shouldSuspend = false;

            try {
                DB::transaction(function () use ($account, $since, $charge, &$charged, &$shouldSuspend) {
                    // Kunci baris layanan lalu baca ulang last_billed_at: dua proses
                    // yang tumpang tindih (cron + artisan manual) tidak boleh menagih
                    // periode yang sama dua kali. Yang kedua melihat last_billed_at
                    // sudah maju dan berhenti.
                    $locked = HostingAccount::query()->lockForUpdate()->find($account->id);
                    $lockedSince = $locked?->last_billed_at ?? $locked?->created_at;

                    if (! $locked || $locked->status !== 'active' || ! $lockedSince || ! $lockedSince->equalTo($since)) {
                        return;
                    }

                    // Saldo dibaca dari baris klien yang terkunci, bukan dari
                    // instance yang dimuat di awal proses.
                    $client = Client::query()->lockForUpdate()->find($locked->client_id);
                    if (! $client) {
                        return;
                    }

                    $balance = (float) $client->balance;
                    $key = 'usage:' . $locked->id . ':' . $since->getTimestamp();

                    if (\App\Support\Money::gte($balance, $charge)) {
                        // Saldo cukup -- potong penuh, layanan tetap jalan.
                        $this->applyCharge($client, $locked, $charge, "Pemakaian {$locked->domain}", $key);
                        $locked->update([
                            'last_billed_at' => now(),
                            'panel_suspend_error' => null,
                        ]);
                        $charged++;

                        return;
                    }

                    // Saldo TIDAK cukup untuk tagihan penuh -- ambil
                    // sisa saldo yang ada (sampai habis, tidak sampai
                    // minus), lalu suspend layanannya. Klien tetap kena
                    // tagih untuk waktu yang SUDAH terpakai, bukan
                    // dibebaskan begitu saja. Selisih yang tidak tertagih
                    // dicatat di ledger dan log supaya kerugiannya terlihat.
                    $shortfall = round($charge - max($balance, 0), 2);

                    if ($balance > 0) {
                        $this->applyCharge(
                            $client,
                            $locked,
                            $balance,
                            "Pemakaian {$locked->domain} (saldo habis di tengah siklus; kurang Rp " . number_format($shortfall, 2, ',', '.') . ' tidak tertagih)',
                            $key,
                        );
                    }

                    Log::warning("Layanan #{$locked->id} {$locked->domain}: saldo habis, Rp " . number_format($shortfall, 2, ',', '.') . ' tidak tertagih.');

                    $locked->update(['last_billed_at' => now()]);
                    $shouldSuspend = true;
                });

                if ($shouldSuspend) {
                    try {
                        $this->suspendProvisioned($account);
                        $account->update([
                            'status' => 'suspended',
                            'panel_suspend_error' => null,
                        ]);
                        $suspended++;
                    } catch (Throwable $e) {
                        // Jangan tandai suspended secara lokal bila panel
                        // menolak permintaan. Status active membuat layanan
                        // tetap masuk proses tagihan dan retry berikutnya.
                        $account->update(['panel_suspend_error' => mb_substr($e->getMessage(), 0, 4000)]);
                        throw $e;
                    }
                }
            } catch (Throwable $e) {
                $failed++;
                Log::error("Gagal memproses tagihan jam untuk hosting_account #{$account->id}: " . $e->getMessage());
                $this->error("  !! Gagal: {$e->getMessage()}");
            }
        }

        if (! $dry) {
            $this->info("Selesai. {$charged} layanan ditagih, {$suspended} disuspend karena saldo habis, {$failed} layanan gagal diproses.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
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
        return HourlyRateCalculator::forAccount($account) ?? 0.0;
    }

    private function applyCharge($client, HostingAccount $account, float $amount, string $description, ?string $idempotencyKey = null): void
    {
        // Semua mutasi saldo wajib lewat CreditService/Client::adjustBalance()
        // agar balance dan ledger client_balance_logs tidak pernah berbeda.
        $this->credits->debit($client, $amount, $description, 'usage_charge', null, null, $idempotencyKey);
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
        if (! $account->server_id) {
            return;
        }

        try {
            if (! $account->serverModel || ! $account->username) {
                throw new RuntimeException('Server panel atau username layanan belum tersedia.');
            }

            $service = HostingPanelFactory::make($account->serverModel);
            $result = $service->suspendAccount($account->username, 'Saldo deposit habis');

            if (! ($result['success'] ?? false)) {
                throw new RuntimeException($result['message'] ?? 'Panel menolak permintaan suspend.');
            }
        } catch (Throwable $e) {
            Log::warning("Layanan #{$account->id} belum disuspend di panel; akan dicoba ulang: " . $e->getMessage());
            throw $e;
        }
    }
}
