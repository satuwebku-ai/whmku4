<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\BillingException;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

/**
 * Canonical top-up flow: create one top-up invoice and credit its balance
 * exactly once after payment. The invoice remains the source of truth for
 * the amount that is credited.
 */
class TopupService
{
    public function createInvoice(Client $client, float $amount): Invoice
    {
        if ($amount < 10000 || $amount > 50000000) {
            throw new BillingException('Nominal isi ulang harus antara Rp 10.000 dan Rp 50.000.000.');
        }

        return DB::transaction(function () use ($client, $amount): Invoice {
            $invoice = Invoice::create([
                'client_id' => $client->id,
                'amount' => $amount,
                'tax' => 0,
                'discount' => 0,
                'status' => 'unpaid',
                'issue_date' => now(),
                'due_date' => now()->addDays(3),
                'is_topup' => true,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Isi Ulang Saldo',
                'amount' => $amount,
            ]);

            return $invoice->refresh();
        });
    }

    /**
     * Apply a paid top-up exactly once. Client::adjustBalance() provides the
     * database lock and idempotency constraint, so queue/webhook retries are
     * safe even when the same invoice is processed more than once.
     */
    public function applyPaidInvoice(Invoice $invoice): void
    {
        // Baca ulang invoice di bawah lock: instance yang dilempar pemanggil bisa
        // usang, dan RefundService mengunci invoice yang sama sebelum menandainya
        // refunded. Urutan kunci (invoice lalu client) sama dengan RefundService
        // dan BillingService sehingga tidak ada deadlock.
        DB::transaction(function () use ($invoice): void {
            $locked = Invoice::query()->lockForUpdate()->find($invoice->id);

            if (! $locked || ! $locked->is_topup || $locked->status !== 'paid') {
                return;
            }

            $client = $locked->client;
            if (! $client) {
                throw new BillingException('Client invoice isi ulang tidak ditemukan.');
            }

            app(CreditService::class)->credit(
                $client,
                (float) $locked->total,
                "Isi ulang saldo — invoice {$locked->invoice_number}",
                'topup',
                $locked,
                null,
                "invoice:{$locked->id}:topup",
            );
        });
    }
}
