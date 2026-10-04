<?php

namespace App\Services\Billing;

use App\Models\ActivityLog;
use App\Models\Credit;
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

            // Top-up lewat gateway yang di-refund / chargeback: saldo yang
            // sudah dikredit dari top-up itu harus ditarik lagi, kalau tidak
            // klien memegang saldo tanpa uang masuk. Boleh jadi minus kalau
            // saldonya sudah terpakai (utang yang terlihat admin).
            if ($invoice->is_topup && $payment->payment_method !== 'Saldo') {
                $this->reverseTopupCredit($payment, $invoice);
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

    private function reverseTopupCredit(Payment $payment, Invoice $invoice): void
    {
        $credited = Credit::query()
            ->where('invoice_id', $invoice->id)
            ->where('type', 'topup')
            ->first();

        // Belum pernah dikredit (mis. refund datang sebelum top-up
        // diproses): tidak ada yang perlu ditarik. Invoice sudah berstatus
        // refunded sehingga kredit juga tidak akan diterapkan belakangan.
        if (! $credited || ! $payment->client) {
            return;
        }

        $client = $payment->client;

        $reversal = $client->adjustBalance(
            -1 * (float) $credited->amount,
            'topup_reversal',
            "Pembatalan isi ulang — refund/chargeback invoice {$invoice->invoice_number}",
            $invoice,
            null,
            'payment:' . $payment->id . ':topup-reversal',
            allowNegative: true,
        );

        if ((float) $reversal->balance_after < 0 && $reversal->wasRecentlyCreated) {
            ActivityLog::record(
                'payment',
                "Saldo {$client->name} MINUS setelah refund/chargeback top-up",
                'Saldo sekarang Rp ' . number_format((float) $reversal->balance_after, 0, ',', '.') . " — invoice {$invoice->invoice_number}. Saldo sudah terpakai sebelum dana ditarik.",
                route('admin.clients.details', $client),
                'danger',
                $client->id,
            );

            try {
                app(\App\Services\Notification\NotificationService::class)->negativeBalance($client, (float) $reversal->balance_after, $invoice->invoice_number);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Peringatan saldo minus gagal terkirim: ' . $e->getMessage());
            }
        }
    }
}
