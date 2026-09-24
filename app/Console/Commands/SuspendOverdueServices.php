<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Setting;
use App\Services\Billing\OverdueServiceLifecycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Suspend otomatis hosting yang invoice perpanjangannya belum dibayar
 * sampai melewati batas toleransi, dan tandai domain kedaluwarsa kalau
 * sudah lewat tanggal expiry-nya sementara masih belum dibayar.
 *
 * Memakai kolom `renewal_invoice_id` yang sama dengan
 * lumora:generate-renewal-invoices — kalau invoice yang dilacak di sana
 * masih belum lunas padahal sudah lewat jatuh tempo + masa toleransi,
 * berarti waktunya disuspend. Kolom itu otomatis terkosongkan lagi begitu
 * invoice dibayar (lihat ProvisioningService::processRenewalPayment),
 * jadi layanan yang sudah lunas tidak pernah tersentuh di sini.
 */
class SuspendOverdueServices extends Command
{
    protected $signature = 'lumora:suspend-overdue
                            {--dry : Hanya tampilkan yang akan diproses, tanpa benar-benar mengubah apa pun}';

    protected $description = 'Suspend hosting & tandai domain kedaluwarsa untuk tagihan yang telat dibayar';

    
    public function handle(): int
    {
        ob_start();
        $result = $this->handleJob();
        $output = ob_get_clean();
        echo $output;

        \App\Models\CronJob::recordExecution('lumora:suspend-overdue', $result === self::SUCCESS, $output);

        return $result;
    }

    private function handleJob(): int
    {
        if (Setting::get('auto_suspend_enabled', '1') !== '1') {
            $this->warn('Auto-suspend sedang dinonaktifkan di Pengaturan → Notifikasi.');

            return self::SUCCESS;
        }

        $graceDays = (int) Setting::get('suspend_grace_days', 3);
        $dry = $this->option('dry');

        $this->info("Masa toleransi: {$graceDays} hari setelah jatuh tempo invoice.");
        $this->newLine();

        $suspended = $this->suspendHosting($graceDays, $dry);
        $expired = $this->expireDomains($dry);

        $this->newLine();
        $this->info($dry
            ? "Simulasi selesai — {$suspended} hosting AKAN disuspend, {$expired} domain AKAN ditandai kedaluwarsa."
            : "Selesai — {$suspended} hosting disuspend, {$expired} domain ditandai kedaluwarsa.");

        return self::SUCCESS;
    }

    /**
     * Hosting aktif dengan invoice perpanjangan yang masih menunggu
     * dibayar, dan sudah lewat jatuh tempo + masa toleransi.
     */
    private function suspendHosting(int $graceDays, bool $dry): int
    {
        $accounts = HostingAccount::with(['client', 'renewalInvoice'])
            ->where('status', 'active')
            ->whereNotNull('renewal_invoice_id')
            ->whereHas('renewalInvoice', function ($q) use ($graceDays) {
                $q->whereIn('status', ['unpaid', 'overdue'])
                  ->whereDate('due_date', '<=', now()->subDays($graceDays)->toDateString());
            })
            ->get();

        $count = 0;
        $lifecycle = app(OverdueServiceLifecycle::class);

        foreach ($accounts as $hosting) {
            if (! $hosting->client) {
                continue;
            }

            $terlambat = (int) now()->diffInDays($hosting->renewalInvoice->due_date);
            $this->line("  [Hosting] {$hosting->domain} — terlambat {$terlambat} hari → {$hosting->client->email}");

            if ($dry) {
                $count++;
                continue;
            }

            try {
                if ($lifecycle->suspendHosting($hosting)) {
                    $count++;
                }
            } catch (Throwable $e) {
                $this->error('        gagal: ' . $e->getMessage());
                Log::error('Auto-suspend gagal: ' . $e->getMessage(), ['hosting_account_id' => $hosting->id]);
            }
        }

        return $count;
    }

    /**
     * Domain aktif yang sudah lewat tanggal expiry sungguhan sementara
     * invoice perpanjangannya masih belum dibayar. Tidak ada "suspend"
     * untuk domain (bukan konsep yang berlaku) — begitu tanggal expiry
     * sungguhan lewat, statusnya cukup diubah jadi "expired". Tidak
     * memakai masa toleransi terpisah karena expiry_date registrar itu
     * sendiri sudah jadi batas kerasnya.
     */
    private function expireDomains(bool $dry): int
    {
        $domains = Domain::with('client', 'renewalInvoice')
            ->where('status', 'active')
            ->whereNotNull('renewal_invoice_id')
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->whereHas('renewalInvoice', fn ($q) => $q->whereIn('status', ['unpaid', 'overdue']))
            ->get();

        $count = 0;
        $lifecycle = app(OverdueServiceLifecycle::class);

        foreach ($domains as $domain) {
            if (! $domain->client) {
                continue;
            }

            $this->line("  [Domain]  {$domain->domain_name} — kedaluwarsa {$domain->expiry_date->format('d M Y')} → {$domain->client->email}");

            if ($dry) {
                $count++;
                continue;
            }

            try {
                if ($lifecycle->expireDomain($domain)) {
                    $count++;
                }
            } catch (Throwable $e) {
                $this->error('        gagal: ' . $e->getMessage());
                Log::error('Auto-expire domain gagal: ' . $e->getMessage(), ['domain_id' => $domain->id]);
            }
        }

        return $count;
    }

}
