<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Setting;
use App\Notifications\ServiceSuspended;
use App\Services\Hosting\HostingPanelFactory;
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
        $accounts = HostingAccount::with(['client', 'renewalInvoice', 'serverModel'])
            ->where('status', 'active')
            ->whereNotNull('renewal_invoice_id')
            ->whereHas('renewalInvoice', function ($q) use ($graceDays) {
                $q->whereIn('status', ['unpaid', 'overdue'])
                  ->whereDate('due_date', '<=', now()->subDays($graceDays)->toDateString());
            })
            ->get();

        $count = 0;

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
                $this->suspendOne($hosting);
                $count++;
            } catch (Throwable $e) {
                $this->error('        gagal: ' . $e->getMessage());
                Log::error('Auto-suspend gagal: ' . $e->getMessage(), ['hosting_account_id' => $hosting->id]);
            }
        }

        return $count;
    }

    private function suspendOne(HostingAccount $hosting): void
    {
        $message = 'Disuspend otomatis: invoice ' . $hosting->renewalInvoice->invoice_number . ' belum dibayar melewati batas toleransi.';

        // Akun yang benar-benar terhubung server dikunci lewat API panel.
        // Akun manual (tanpa server) hanya diubah statusnya di database —
        // tidak ada apa pun di luar sistem ini yang perlu/bisa dikunci.
        if ($hosting->serverModel && $hosting->username) {
            $result = HostingPanelFactory::make($hosting->serverModel)->suspendAccount(
                $hosting->username,
                'Tagihan belum dibayar: ' . $hosting->renewalInvoice->invoice_number
            );

            if (! $result['success']) {
                Log::warning('Suspend API gagal, status database tetap diperbarui: ' . $result['message'], [
                    'hosting_account_id' => $hosting->id,
                ]);
            }

            $message = $result['message'] ?? $message;
        }

        $hosting->update([
            'status' => 'suspended',
            'provision_message' => $message,
        ]);

        ActivityLog::record(
            'service',
            'Hosting disuspend otomatis: ' . $hosting->domain,
            'Invoice ' . $hosting->renewalInvoice->invoice_number . ' belum dibayar.',
            route('admin.hosting-accounts.details', $hosting),
            'danger',
            $hosting->client_id,
        );

        if (Setting::get('notify_suspend', '1') === '1') {
            try {
                $hosting->client->notify(new ServiceSuspended($hosting->domain, $hosting->renewalInvoice));
            } catch (Throwable $e) {
                Log::warning('Notifikasi suspend gagal: ' . $e->getMessage());
            }
        }
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

        foreach ($domains as $domain) {
            if (! $domain->client) {
                continue;
            }

            $this->line("  [Domain]  {$domain->domain_name} — kedaluwarsa {$domain->expiry_date->format('d M Y')} → {$domain->client->email}");

            if ($dry) {
                $count++;
                continue;
            }

            $domain->update([
                'status' => 'expired',
                'provision_message' => 'Kedaluwarsa otomatis: invoice perpanjangan belum dibayar sampai lewat tanggal expiry.',
            ]);

            ActivityLog::record(
                'domain',
                'Domain kedaluwarsa: ' . $domain->domain_name,
                'Invoice perpanjangan belum dibayar.',
                route('admin.domains.details', $domain),
                'danger',
                $domain->client_id,
            );

            $count++;
        }

        return $count;
    }
}
