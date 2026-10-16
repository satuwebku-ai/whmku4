<?php

namespace App\Console\Commands;

use App\Services\Billing\InvoiceItemServiceBackfill;
use Illuminate\Console\Command;

/**
 * Isi rujukan layanan (domain/hosting) pada baris invoice lama. Jalankan
 * ulang kapan saja, mis. setelah memulihkan data dari seeder, atau berkala
 * agar invoice hasil checkout baru (yang layanannya dibuat setelah bayar)
 * ikut terhubung.
 */
class BackfillInvoiceServices extends Command
{
    protected $signature = 'lumora:backfill-invoice-services
                            {--dry : Hanya hitung, tanpa menulis ke database}';

    protected $description = 'Isi service_type/service_id/item_type pada invoice_items lama';

    public function handle(InvoiceItemServiceBackfill $backfill): int
    {
        $stats = $backfill->run((bool) $this->option('dry'));

        if (isset($stats['error'])) {
            $this->error($stats['error']);

            return self::FAILURE;
        }

        $this->info(($this->option('dry') ? 'Simulasi — ' : '')
            . "cocok lewat deskripsi: {$stats['matched']}, lewat order: {$stats['by_order']}, "
            . "ambigu (dilewati): {$stats['ambiguous']}, layanan tidak ditemukan: {$stats['not_found']}, "
            . "tidak dikenali: {$stats['unparsed']}.");

        return self::SUCCESS;
    }
}
