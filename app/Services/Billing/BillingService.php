<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\BillingException;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrator lapisan Billing — merangkai InvoiceService + CreditService
 * untuk alur yang melibatkan keduanya sekaligus (bayar invoice pakai
 * saldo). Logic gabungan ini sebelumnya ada langsung di
 * Client\BalanceController::payWithBalance().
 */
class BillingService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly CreditService $credit,
    ) {
    }

    /**
     * Bayar invoice pakai saldo klien. Kalau berhasil, invoice langsung
     * lunas lewat Payment::markAsPaid() supaya seluruh hook yang sama
     * seperti pembayaran gateway (provisioning, perpanjangan, upgrade)
     * ikut terpicu — bayar pakai saldo diperlakukan identik dengan
     * metode pembayaran lain, bukan jalur pintas terpisah.
     *
     * @throws BillingException invoice tidak bisa dibayar (lunas/batal/topup) atau saldo tidak cukup
     */
    public function payInvoiceWithBalance(Invoice $invoice, Client $client): Payment
    {
        return DB::transaction(function () use ($invoice, $client) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);

            $this->invoices->assertPayable($invoice);

            if ($invoice->is_topup) {
                throw new BillingException('Invoice isi ulang saldo tidak bisa dibayar pakai saldo.');
            }

            $amount = (float) $invoice->total;
            if ((float) $client->balance < $amount) {
                throw new BillingException('Saldo Anda tidak cukup untuk membayar invoice ini.');
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'client_id' => $client->id,
                'payment_gateway_id' => null,
                'amount' => $invoice->amount,
                'fee' => 0,
                'total' => $amount,
                'currency' => 'IDR',
                'status' => 'initiated',
                'payment_method' => 'Saldo',
            ]);

            $this->credit->debit(
                $client,
                $amount,
                "Bayar invoice {$invoice->invoice_number}",
                'payment',
                $invoice,
                null,
                'payment:' . $payment->id . ':balance-debit',
            );

            $payment->markAsPaid('Saldo');

            return $payment->fresh();
        });
    }
}
