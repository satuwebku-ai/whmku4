<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bersihkan invoice domain yang dibuat lebih dari 30 hari sebelum
     * expiry_date. Sebelum perbaikan, nilai jendela hosting (bisa sampai
     * 60 hari) ikut dipakai oleh domain.
     */
    public function up(): void
    {
        $domains = DB::table('domains')
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereNotNull('renewal_invoice_id')
            ->get(['id', 'expiry_date', 'renewal_invoice_id']);

        foreach ($domains as $domain) {
            $invoice = DB::table('invoices')
                ->where('id', $domain->renewal_invoice_id)
                ->first();

            if (! $invoice || ! in_array($invoice->status, ['unpaid', 'overdue'], true)) {
                continue;
            }

            $cutoff = Carbon::parse($domain->expiry_date)->subDays(30);

            if (Carbon::parse($invoice->issue_date)->gte($cutoff)) {
                continue;
            }

            $note = trim((string) $invoice->notes);
            $cancellationNote = 'Dibatalkan otomatis karena invoice domain dibuat sebelum jendela renewal H-30.';

            DB::table('invoices')
                ->where('id', $invoice->id)
                ->update([
                    'status' => 'cancelled',
                    'notes' => $note
                        ? $note . "\n" . $cancellationNote
                        : $cancellationNote,
                    'updated_at' => now(),
                ]);

            DB::table('domains')
                ->where('id', $domain->id)
                ->update([
                    'renewal_invoice_id' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Data cleanup tidak dibalikkan saat rollback karena relasi dan status
     * invoice sebelumnya tidak disimpan di tempat lain.
     */
    public function down(): void
    {
        //
    }
};