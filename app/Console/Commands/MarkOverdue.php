<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Billing\InvoiceService;
use Illuminate\Console\Command;

/**
 * Ubah status invoice yang sudah melewati jatuh tempo.
 *
 * Dipisahkan dari pengingat karena berjalan lebih sering: status di panel
 * sebaiknya akurat sepanjang hari, sementara email pengingat cukup sekali.
 */
class MarkOverdue extends Command
{
    protected $signature = 'lumora:mark-overdue';

    protected $description = 'Tandai invoice yang melewati jatuh tempo sebagai overdue';

    
    public function handle(InvoiceService $invoices): int
    {
        ob_start();
        $result = $this->handleJob($invoices);
        $output = ob_get_clean();
        echo $output;

        \App\Models\CronJob::recordExecution('lumora:mark-overdue', $result === self::SUCCESS, $output);

        return $result;
    }

    private function handleJob(InvoiceService $invoices): int
    {
        $count = 0;

        Invoice::where('status', 'unpaid')
            ->whereDate('due_date', '<', now()->toDateString())
            ->chunkById(100, function ($invoicesToMark) use ($invoices, &$count) {
                foreach ($invoicesToMark as $invoice) {
                    if ($invoices->markOverdue($invoice)->status === 'overdue') {
                        $count++;
                    }
                }
            });

        $this->info($count > 0
            ? "{$count} invoice ditandai lewat tempo."
            : 'Tidak ada invoice yang perlu ditandai.');

        return self::SUCCESS;
    }
}
