<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Membuat invoice perpanjangan otomatis untuk hosting & domain yang masa
 * aktifnya mendekati habis — jantung dari pendapatan berulang bisnis
 * hosting. Tanpa perintah ini, layanan yang sudah lewat jatuh tempo tidak
 * pernah ditagih ulang secara otomatis; admin harus mengingat dan membuat
 * invoice perpanjangan satu per satu secara manual.
 *
 * Dijalankan harian lewat scheduler (lihat routes/console.php).
 */
class GenerateRenewalInvoices extends Command
{
    protected $signature = 'lumora:generate-renewal-invoices
                            {--dry : Hanya tampilkan yang akan dibuat, tanpa benar-benar membuat invoice}';

    protected $description = 'Buat invoice perpanjangan untuk hosting & domain yang mendekati jatuh tempo';

    
    public function handle(): int
    {
        ob_start();
        $result = $this->handleJob();
        $output = ob_get_clean();
        echo $output;

        \App\Models\CronJob::recordExecution('lumora:generate-renewal-invoices', $result === self::SUCCESS, $output);

        return $result;
    }

    private function handleJob(): int
    {
        $daysBefore = (int) Setting::get('renewal_invoice_days_before', 7);
        $dry = $this->option('dry');

        $this->info("Jendela pembuatan invoice: H-{$daysBefore} sebelum jatuh tempo.");
        $this->newLine();

        $hostingCount = $this->processHosting($daysBefore, $dry);
        $domainCount = $this->processDomains($daysBefore, $dry);

        $this->newLine();
        $this->info($dry
            ? "Simulasi selesai — {$hostingCount} invoice hosting + {$domainCount} invoice domain AKAN dibuat."
            : "Selesai — {$hostingCount} invoice hosting + {$domainCount} invoice domain berhasil dibuat.");

        return self::SUCCESS;
    }

    /**
     * Proses hosting account yang aktif dan mendekati next_due_date.
     */
    private function processHosting(int $daysBefore, bool $dry): int
    {
        $accounts = HostingAccount::with('client')
            ->where('status', 'active')
            ->whereNotNull('next_due_date')
            ->whereNull('renewal_invoice_id') // belum ada invoice perpanjangan yang menunggu
            ->whereDate('next_due_date', '<=', now()->addDays($daysBefore)->toDateString())
            ->get();

        $count = 0;

        foreach ($accounts as $hosting) {
            if (! $hosting->client) {
                continue;
            }

            $this->line("  [Hosting] {$hosting->domain} — jatuh tempo {$hosting->next_due_date->format('d M Y')} → {$hosting->client->email}");

            if ($dry) {
                $count++;
                continue;
            }

            try {
                DB::transaction(fn () => $hosting->createRenewalInvoice());
                $count++;
            } catch (Throwable $e) {
                $this->error('        gagal: ' . $e->getMessage());
                Log::error('Gagal membuat invoice perpanjangan hosting: ' . $e->getMessage(), ['hosting_account_id' => $hosting->id]);
            }
        }

        return $count;
    }

    /**
     * Proses domain yang aktif, auto_renew menyala, dan mendekati expiry_date.
     */
    private function processDomains(int $daysBefore, bool $dry): int
    {
        $domains = Domain::with('client', 'tld')
            ->where('status', 'active')
            ->where('auto_renew', true)
            ->whereNotNull('expiry_date')
            ->whereNull('renewal_invoice_id')
            ->whereDate('expiry_date', '<=', now()->addDays($daysBefore)->toDateString())
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

            try {
                DB::transaction(fn () => $domain->createRenewalInvoice());
                $count++;
            } catch (Throwable $e) {
                $this->error('        gagal: ' . $e->getMessage());
                Log::error('Gagal membuat invoice perpanjangan domain: ' . $e->getMessage(), ['domain_id' => $domain->id]);
            }
        }

        return $count;
    }
}
