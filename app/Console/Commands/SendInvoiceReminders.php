<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Mengirim pengingat tagihan: sebelum jatuh tempo (H-n) dan setelah lewat.
 *
 * Dijalankan oleh dispatcher pusat. Saat cron terlambat, hanya tahap terbaru
 * yang masih relevan yang dikirim; tahap lama tidak dikirim bertubi-tubi.
 */
class SendInvoiceReminders extends Command
{
    protected $signature = 'lumora:send-reminders
                            {--dry : Hanya tampilkan siapa yang akan dikirimi, tanpa benar-benar mengirim}';

    protected $description = 'Kirim pengingat tagihan yang akan / sudah jatuh tempo';

    public function handle(NotificationService $notifications): int
    {
        if (Setting::get('notify_reminder', '1') !== '1') {
            $this->warn('Pengingat tagihan sedang dinonaktifkan di Pengaturan → Notifikasi.');

            return self::SUCCESS;
        }

        // Hari-hari sebelum jatuh tempo yang dikirimi pengingat, mis. "7,3,1".
        $beforeDays = collect(explode(',', (string) Setting::get('reminder_days_before', '7,3,1')))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0)
            ->unique()
            ->sort()
            ->values();

        // Hari-hari setelah lewat jatuh tempo, mis. "1,7".
        $afterDays = collect(explode(',', (string) Setting::get('reminder_days_after', '1,7')))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0)
            ->unique()
            ->sort()
            ->values();

        $dry = $this->option('dry');
        $eligible = 0;
        $queued = 0;
        $failed = 0;

        $this->info('Pengingat sebelum jatuh tempo: H-' . $beforeDays->implode(', H-'));
        $this->info('Pengingat setelah jatuh tempo: H+' . $afterDays->implode(', H+'));
        $this->newLine();

        $latestUpcomingReminder = (int) ($beforeDays->max() ?? 0);
        $cutoff = today()->addDays($latestUpcomingReminder)->toDateString();

        Invoice::with('client')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', '<=', $cutoff)
            ->chunkById(100, function ($invoices) use ($beforeDays, $afterDays, $dry, $notifications, &$eligible, &$queued, &$failed) {
                foreach ($invoices as $invoice) {
                    $daysUntilDue = (int) today()->diffInDays($invoice->due_date->copy()->startOfDay(), false);

                    // Perbarui status lewat tempo meski tidak ada tahap
                    // pengingat H+ yang dikonfigurasi.
                    if ($daysUntilDue < 0 && $invoice->status === 'unpaid' && ! $dry) {
                        $invoice->markOverdue();
                    }

                    $stage = $this->latestApplicableStage($daysUntilDue, $beforeDays, $afterDays);

                    if (! $stage) {
                        continue;
                    }

                    if (! $invoice->client) {
                        $failed++;
                        $this->error("  Invoice {$invoice->invoice_number} tidak memiliki klien untuk pengiriman pengingat.");
                        continue;
                    }

                    $label = $daysUntilDue < 0 ? 'H+' . abs($daysUntilDue) : 'H-' . $daysUntilDue;
                    $this->line("  [{$label}; {$stage['key']}] {$invoice->invoice_number} → {$invoice->client->email}");
                    $eligible++;

                    if (! $dry) {
                        $result = $notifications->invoiceReminder($invoice, $daysUntilDue, $stage['key']);

                        if ($result === 'queued') {
                            $queued++;
                        } elseif ($result === 'failed') {
                            $failed++;
                            $this->error("  Gagal mengantrikan pengingat {$invoice->invoice_number} ({$stage['key']}).");
                        }
                    }
                }
            });

        $this->newLine();
        $this->info($dry
            ? "Simulasi selesai — {$eligible} invoice memenuhi syarat; tidak ada pesan dikirim."
            : "Selesai — {$queued} pengingat baru diantrikan dari {$eligible} invoice yang memenuhi syarat, {$failed} gagal.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Pilih satu tahap yang paling baru terlewati untuk setiap invoice.
     */
    private function latestApplicableStage(int $daysUntilDue, Collection $beforeDays, Collection $afterDays): ?array
    {
        if ($daysUntilDue >= 0) {
            $configuredDays = $beforeDays
                ->filter(fn ($days) => $daysUntilDue <= $days)
                ->min();

            return $configuredDays === null
                ? null
                : ['key' => "before:{$configuredDays}"];
        }

        $daysLate = abs($daysUntilDue);
        $configuredDays = $afterDays
            ->filter(fn ($days) => $days <= $daysLate)
            ->max();

        return $configuredDays === null
            ? null
            : ['key' => "after:{$configuredDays}"];
    }
}
