<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Satu jalur idempotent untuk refund/chargeback payment.
 *
 * Refund tidak menghapus payment atau charge lama. Ia menambahkan transaksi
 * pembalik, menandai invoice sebagai refunded, dan mengembalikan saldo bila
 * sumber pembayaran memang saldo internal. Fulfillment baru tidak akan
 * berjalan lagi karena ProcessPaidInvoice hanya memproses invoice paid.
 *
 * Penghentian layanan yang sudah terlanjur aktif sengaja tidak ditebak di
 * sini; kebijakan suspend/terminate perlu dipilih per tipe layanan oleh
 * operator bisnis sebelum dibuat otomatis.
 */
class RefundService
{
    public function apply(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment = Payment::query()->lockForUpdate()->find($payment->id);
            if (! $payment || $payment->status !== 'refunded') {
                return;
            }

            $invoice = Invoice::query()->lockForUpdate()->find($payment->invoice_id);
            if (! $invoice) {
                return;
            }

            $amount = (float) $payment->total;

            Transaction::firstOrCreate(
                ['idempotency_key' => 'payment:' . $payment->id . ':refund'],
                [
                    'client_id' => $payment->client_id,
                    'invoice_id' => $payment->invoice_id,
                    'payment_id' => $payment->id,
                    'type' => 'refund',
                    'amount' => -$amount,
                    'description' => 'Refund pembayaran invoice ' . ($invoice->invoice_number ?? $invoice->id),
                ]
            );

            if ($invoice->status !== 'refunded') {
                $invoice->update([
                    'status' => 'refunded',
                    'notes' => trim((string) $invoice->notes . "\nRefund payment {$payment->reference} tercatat."),
                ]);
            }

            if ($payment->payment_method === 'Saldo') {
                app(CreditService::class)->credit(
                    $payment->client,
                    $amount,
                    "Refund pembayaran invoice {$invoice->invoice_number}",
                    'refund',
                    $invoice,
                    null,
                    'payment:' . $payment->id . ':refund-credit',
                );
            }
        });
    }
}