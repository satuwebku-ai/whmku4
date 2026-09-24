<?php

namespace App\Models;

use App\Events\Invoice\InvoicePaid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Enums\OrderStatus;
use App\Models\Transaction;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'invoice_id', 'client_id', 'payment_gateway_id',
        'amount', 'fee', 'total', 'currency', 'status', 'external_id',
        'payment_method', 'payment_url', 'proof_path', 'gateway_response',
        'admin_note', 'paid_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'total' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->reference)) {
                $payment->reference = static::generateReference();
            }

            if (empty($payment->total)) {
                $payment->total = (float) $payment->amount + (float) $payment->fee;
            }
        });

        static::updated(function (Payment $payment) {
            if ($payment->wasChanged('status') && $payment->status === 'refunded') {
                app(\App\Services\Affiliate\AffiliateCommissionService::class)
                    ->reverseForInvoice($payment->invoice_id, 'Payment invoice direfund/chargeback otomatis.');
                app(\App\Services\Billing\RefundService::class)->apply($payment);
            }
        });
    }

    public static function generateReference(): string
    {
        $year = now()->year;
        $last = static::whereYear('created_at', $year)->orderByDesc('id')->first();
        $lastNumber = $last && preg_match('/(\d+)$/', (string) $last->reference, $matches)
            ? (int) $matches[1]
            : 0;
        $next = \App\Models\NumberSequence::nextAtLeast('payments:' . $year, $lastNumber + 1);

        return "PAY-{$year}-" . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Tandai lunas dan otomatis lunasi invoice terkait.
     */
    public function markAsPaid(?string $method = null, array $raw = []): void
    {
        $invoiceWasPaid = DB::transaction(function () use ($method, $raw): bool {
            $payment = static::query()->lockForUpdate()->findOrFail($this->id);
            $invoice = Invoice::query()->lockForUpdate()->find($payment->invoice_id);

            if (! $invoice || $payment->status === 'paid') {
                return false;
            }

            // Hanya pembayaran yang masih menunggu yang boleh masuk ke state
            // paid. Payment failed/expired/refunded tidak boleh dihidupkan
            // kembali oleh approval admin atau webhook yang terlambat.
            if (! in_array($payment->status, ['initiated', 'pending'], true)) {
                return false;
            }

            if ($invoice->status === 'cancelled') {
                throw new \App\Exceptions\Billing\InvoiceException(
                    "Invoice {$invoice->invoice_number} sudah dibatalkan dan tidak bisa dibayar."
                );
            }

            // Webhook/callback tidak boleh menjadi jalur pembayaran alternatif
            // yang melewati aturan invoice dan dokumen. Semua entry point
            // memakai service yang sama sebelum fulfillment dijalankan.
            $eligibility = app(\App\Services\Payment\PaymentEligibilityService::class)->check($invoice);
            if (! $eligibility['allowed']) {
                $payment->update([
                    'status' => 'expired',
                    'gateway_response' => $raw ?: $payment->gateway_response,
                    'admin_note' => trim(($payment->admin_note ? $payment->admin_note . ' ' : '')
                        . '[Otomatis] Callback ditolak: ' . ($eligibility['message'] ?? 'invoice tidak dapat dibayar.')),
                ]);

                return false;
            }

            // Invoice sudah lunas lewat pembayaran lain. Payment kedua
            // ditutup sebagai expired, bukan ikut ditandai paid.
            if ($invoice->status === 'paid') {
                $payment->update([
                    'status' => 'expired',
                    'admin_note' => trim(($payment->admin_note ? $payment->admin_note . ' ' : '')
                        . '[Otomatis] Pembayaran ditutup karena invoice sudah lunas lewat pembayaran lain.'),
                ]);

                return false;
            }

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $method ?? $payment->payment_method,
                'gateway_response' => $raw ?: $payment->gateway_response,
            ]);

            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $payment->gateway?->name ?? $method,
            ]);

            // Catat charge finansial tepat sekali. Webhook replay tidak boleh
            // membuat transaksi ledger kedua. Constraint DB pada
            // idempotency_key menjadi lapisan terakhir selain firstOrCreate.
            Transaction::firstOrCreate(
                ['idempotency_key' => 'payment:' . $payment->id . ':paid'],
                [
                    'client_id' => $payment->client_id,
                    'invoice_id' => $payment->invoice_id,
                    'payment_id' => $payment->id,
                    'type' => 'charge',
                    'amount' => $payment->total,
                    'description' => 'Pembayaran invoice ' . ($invoice->invoice_number ?? $invoice->id),
                ]
            );

            // Payment completion advances every service order attached to
            // this invoice. invoice_items is the canonical multi-order path;
            // invoices.order_id remains supported for legacy single-order invoices.
            $orders = $invoice->items()->with('order')->get()->pluck('order')->filter();
            if ($invoice->order) {
                $orders->push($invoice->order);
            }
            foreach ($orders->unique('id') as $order) {
                if ($order->status === OrderStatus::PendingPayment) {
                    $order->markPaid('Invoice pembayaran terverifikasi.');
                }
            }

            // Payment::markAsPaid adalah satu-satunya titik yang memicu
            // fulfillment. Listener hanya memasukkan invoice ke queue billing.
            static::where('invoice_id', $payment->invoice_id)
                ->where('id', '!=', $payment->id)
                ->whereIn('status', ['initiated', 'pending'])
                ->update([
                    'status' => 'expired',
                    'admin_note' => 'Dibatalkan otomatis: invoice sudah lunas lewat ' . $payment->reference . '.',
                ]);

            return true;
        });

        if ($invoiceWasPaid) {
            InvoicePaid::dispatch(Invoice::findOrFail($this->invoice_id));
        }
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'paid',
            'pending', 'initiated' => 'pending',
            'refunded' => 'inactive',
            default => 'suspended',
        };
    }
}
