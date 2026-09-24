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
        if (! $invoice->is_topup || $invoice->status !== 'paid') {
            return;
        }

        $client = $invoice->client;
        if (! $client) {
            throw new BillingException('Client invoice isi ulang tidak ditemukan.');
        }

        app(CreditService::class)->credit(
            $client,
            (float) $invoice->total,
            "Isi ulang saldo — invoice {$invoice->invoice_number}",
            'topup',
            $invoice,
            null,
            "invoice:{$invoice->id}:topup",
        );
    }
}
