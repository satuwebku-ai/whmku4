<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\InvoiceAlreadyPaidException;
use App\Exceptions\Billing\InvoiceException;
use App\Exceptions\Billing\InvoiceNotFoundException;
use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payment\PaymentEligibilityService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Sentralisasi transisi status invoice (lunas / batal / belum lunas) yang
 * sebelumnya ditulis ulang secara terpisah di
 * Admin\InvoiceController::markPaid()/cancel() dan
 * Client\InvoiceController::pay(). Model Invoice tetap memegang rumus
 * total dan notifikasi penerbitan — service ini hanya
 * bertanggung jawab atas ATURAN transisi (boleh/tidak boleh) dan efek
 * samping lintas-tabel (melepas renewal_invoice_id saat dibatalkan).
 */
class InvoiceService
{
    public function findOrFail(int $id): Invoice
    {
        try {
            return Invoice::findOrFail($id);
        } catch (ModelNotFoundException) {
            throw InvoiceNotFoundException::forId($id);
        }
    }

    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create($data);

            if ($invoice->total < 0) {
                throw new InvoiceException('Total invoice tidak valid.');
            }

            return $invoice;
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $targetStatus = $data['status'] ?? null;
        $paymentMethod = $data['payment_method'] ?? null;
        unset($data['status'], $data['paid_at']);
        $invoice->update($data);

        if ($targetStatus === 'paid' && $invoice->status !== 'paid') {
            return $this->markPaid($invoice->fresh(), $paymentMethod);
        }

        if ($targetStatus === 'cancelled' && $invoice->status !== 'cancelled') {
            return $this->cancel($invoice->fresh());
        }

        if ($targetStatus === 'unpaid' && $invoice->status !== 'unpaid') {
            return $this->markUnpaid($invoice->fresh());
        }

        if ($targetStatus === 'overdue' && $invoice->status !== 'overdue') {
            return $this->markOverdue($invoice->fresh());
        }

        if ($targetStatus === 'refunded' && $invoice->status !== 'refunded') {
            throw new InvoiceException('Status refunded hanya boleh dibuat melalui proses refund payment.');
        }

        return $invoice->fresh();
    }

    /**
     * Pastikan invoice ini masih boleh dibayar. Dipakai di semua jalur
     * pembayaran (gateway, saldo, manual) supaya aturan "tidak boleh bayar
     * invoice yang sudah lunas/batal" hanya ditulis satu kali.
     *
     * @throws InvoiceAlreadyPaidException
     * @throws InvoiceException
     */
    public function assertPayable(Invoice $invoice): void
    {
        app(PaymentEligibilityService::class)->assertPayable($invoice);
    }

    /**
     * Tandai invoice lunas secara manual (dari panel admin). Pembayaran
     * lewat gateway/saldo juga berakhir di Payment::markAsPaid(). Jalur
     * manual admin membuat payment pending bila belum ada, lalu memakai
     * transisi yang sama agar fulfillment tidak punya jalan kedua.
     */
    public function markPaid(Invoice $invoice, ?string $paymentMethod = null): Invoice
    {
        $this->assertPayable($invoice);

        $payment = DB::transaction(function () use ($invoice, $paymentMethod) {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $payment = $lockedInvoice->payments()
                ->whereIn('status', ['initiated', 'pending'])
                ->latest('id')
                ->first();

            return $payment ?? Payment::create([
                'invoice_id' => $lockedInvoice->id,
                'client_id' => $lockedInvoice->client_id,
                'amount' => $lockedInvoice->amount,
                'fee' => 0,
                'total' => $lockedInvoice->total,
                'currency' => 'IDR',
                'status' => 'pending',
                'payment_method' => $paymentMethod ?? 'Manual',
            ]);
        });

        $payment->markAsPaid($paymentMethod ?? 'Manual');

        return $invoice->fresh();
    }

    public function markUnpaid(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'paid') {
            throw new InvoiceException('Invoice yang sudah lunas tidak boleh dikembalikan ke unpaid secara manual. Buat refund/adjustment sebagai transaksi terpisah.');
        }

        $invoice->update(['status' => 'unpaid', 'paid_at' => null]);

        return $invoice->fresh();
    }

    public function markOverdue(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'paid' || $invoice->status === 'cancelled') {
            return $invoice;
        }

        $invoice->markOverdue();

        return $invoice->fresh();
    }

    /**
     * Batalkan invoice dan lepas keterkaitan renewal_invoice_id di
     * domain/hosting terkait — tanpa ini, layanan yang keterlanjur
     * ditautkan ke invoice ini macet permanen (lihat komentar asli di
     * Admin\InvoiceController sebelum dipindah ke sini).
     */
    public function cancel(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === 'paid') {
                throw new InvoiceException('Invoice yang sudah lunas tidak boleh dibatalkan. Gunakan proses refund.');
            }

            if ($invoice->status === 'cancelled') {
                return $invoice;
            }

            $invoice->update(['status' => 'cancelled', 'paid_at' => null]);

            // Payment yang masih menunggu tidak boleh menerima webhook lama
            // setelah invoice dibatalkan.
            $invoice->payments()
                ->whereIn('status', ['initiated', 'pending'])
                ->update([
                    'status' => 'expired',
                    'admin_note' => 'Dibatalkan karena invoice dibatalkan.',
                ]);

            Domain::where('renewal_invoice_id', $invoice->id)
                ->update(['renewal_invoice_id' => null]);
            Domain::where('privacy_invoice_id', $invoice->id)
                ->update(['privacy_invoice_id' => null]);

            HostingAccount::where('renewal_invoice_id', $invoice->id)
                ->update(['renewal_invoice_id' => null]);
            HostingAccount::where('pending_upgrade_invoice_id', $invoice->id)
                ->update([
                    'pending_upgrade_invoice_id' => null,
                    'pending_upgrade_product_id' => null,
                ]);

            return $invoice->fresh();
        });
    }
}
