<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bersihkan data lama yang sudah telanjur memiliki invoice renewal saat
     * layanan hosting-nya telah terminated.
     */
    public function up(): void
    {
        $invoiceIds = DB::table('hosting_accounts')
            ->where('status', 'terminated')
            ->whereNotNull('renewal_invoice_id')
            ->pluck('renewal_invoice_id');

        foreach ($invoiceIds as $invoiceId) {
            $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

            if ($invoice && in_array($invoice->status, ['unpaid', 'overdue'], true)) {
                $note = trim((string) $invoice->notes);
                $cancellationNote = 'Dibatalkan otomatis karena layanan sudah terminated.';

                DB::table('invoices')
                    ->where('id', $invoiceId)
                    ->update([
                        'status' => 'cancelled',
                        'notes' => $note
                            ? $note . "\n" . $cancellationNote
                            : $cancellationNote,
                        'updated_at' => now(),
                    ]);
            }
        }

        DB::table('hosting_accounts')
            ->where('status', 'terminated')
            ->whereNotNull('renewal_invoice_id')
            ->update([
                'renewal_invoice_id' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Data cleanup ini tidak dibalikkan saat rollback karena relasi dan
     * status invoice sebelumnya tidak disimpan di tempat lain.
     */
    public function down(): void
    {
        //
    }
};