<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Setting;
use App\Notifications\InvoiceDueReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim pengingat tagihan: sebelum jatuh tempo (H-n) dan setelah lewat.
 *
 * Dijalankan sekali sehari lewat cron. Karena berjalan otomatis, hasilnya
 * dicetak ke layar supaya bisa diperiksa manual saat pertama kali dipasang.
 */
class SendInvoiceReminders extends Command
{
    protected $signature = 'lumora:send-reminders
                            {--dry : Hanya tampilkan siapa yang akan dikirimi, tanpa benar-benar mengirim}';

    protected $description = 'Kirim pengingat tagihan yang akan / sudah jatuh tempo';

    
    public function handle(): int
    {
        ob_start();
        $result = $this->handleJob();
        $output = ob_get_clean();
        echo $output;

        \App\Models\CronJob::recordExecution('lumora:send-reminders', $result === self::SUCCESS, $output);

        return $result;
    }

    private function handleJob(): int
    {
        // Dicatat sebelum pengecekan lain: tujuannya membuktikan cron
        // benar-benar berjalan, terlepas dari apakah ada yang dikirim.
        Setting::put('last_cron_run', now()->toDateTimeString(), 'system');

        if (Setting::get('notify_reminder', '1') !== '1') {
            $this->warn('Pengingat tagihan sedang dinonaktifkan di Pengaturan → Notifikasi.');

            return self::SUCCESS;
        }

        // Hari-hari sebelum jatuh tempo yang dikirimi pengingat, mis. "7,3,1".
        $beforeDays = collect(explode(',', (string) Setting::get('reminder_days_before', '7,3,1')))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0)
            ->unique();

        // Hari-hari setelah lewat jatuh tempo, mis. "1,7".
        $afterDays = collect(explode(',', (string) Setting::get('reminder_days_after', '1,7')))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0)
            ->unique();

        $dry = $this->option('dry');
        $sent = 0;

        $this->info('Pengingat sebelum jatuh tempo: H-' . $beforeDays->implode(', H-'));
        $this->info('Pengingat setelah jatuh tempo: H+' . $afterDays->implode(', H+'));
        $this->newLine();

        foreach ($beforeDays as $days) {
            $sent += $this->processDate(now()->addDays($days)->toDateString(), $days, $dry);
        }

        foreach ($afterDays as $days) {
            $sent += $this->processDate(now()->subDays($days)->toDateString(), -$days, $dry);
        }

        $this->newLine();
        $this->info($dry
            ? "Simulasi selesai — {$sent} tagihan akan dikirimi pengingat."
            : "Selesai — {$sent} pengingat terkirim.");

        return self::SUCCESS;
    }

    /**
     * Proses semua invoice yang jatuh tempo pada tanggal tertentu.
     */
    private function processDate(string $date, int $daysLeft, bool $dry): int
    {
        $invoices = Invoice::with('client')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', $date)
            ->get();

        $count = 0;

        foreach ($invoices as $invoice) {
            if (! $invoice->client) {
                continue;
            }

            $label = $daysLeft < 0 ? 'H+' . abs($daysLeft) : 'H-' . $daysLeft;
            $this->line("  [{$label}] {$invoice->invoice_number} → {$invoice->client->email}");

            if ($dry) {
                $count++;
                continue;
            }

            try {
                $invoice->client->notify(new InvoiceDueReminder($invoice, $daysLeft));
                $count++;
            } catch (Throwable $e) {
                $this->error("        gagal: " . $e->getMessage());
                Log::warning('Pengingat tagihan gagal: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
            }
        }

        // Tandai lewat tempo sekalian, supaya status di panel tetap akurat
        // walau tidak ada yang membuka halamannya.
        if ($daysLeft < 0 && ! $dry) {
            Invoice::where('status', 'unpaid')
                ->whereDate('due_date', '<', now()->toDateString())
                ->update(['status' => 'overdue']);
        }

        return $count;
    }
}
