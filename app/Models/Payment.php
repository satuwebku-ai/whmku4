<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
    }

    public static function generateReference(): string
    {
        $year = now()->year;
        $last = static::whereYear('created_at', $year)->orderByDesc('id')->first();
        $next = $last ? ((int) Str::afterLast($last->reference, '-') + 1) : 1;

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

    /**
     * Tandai lunas dan otomatis lunasi invoice terkait.
     */
    public function markAsPaid(?string $method = null, array $raw = []): void
    {
        // Invoice sudah lunas lewat pembayaran lain — jangan diproses lagi.
        // Ini bisa terjadi kalau klien membayar via dua jalur, atau webhook
        // gateway datang setelah admin menyetujui transfer manual.
        if ($this->invoice && $this->invoice->status === 'paid' && $this->status !== 'paid') {
            $this->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $method ?? $this->payment_method,
                'gateway_response' => $raw ?: $this->gateway_response,
                'admin_note' => trim(($this->admin_note ? $this->admin_note . ' ' : '')
                    . '[Otomatis] Invoice sudah lunas lewat pembayaran lain.'),
            ]);

            return;
        }

        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $method ?? $this->payment_method,
            'gateway_response' => $raw ?: $this->gateway_response,
        ]);

        $this->invoice?->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $this->gateway->name ?? $method,
        ]);

        // Pembayaran lain yang masih menggantung untuk invoice yang sama
        // dibatalkan otomatis. Tanpa ini, admin melihat transaksi "pending"
        // selamanya untuk invoice yang sebenarnya sudah lunas.
        if ($this->invoice) {
            static::where('invoice_id', $this->invoice_id)
                ->where('id', '!=', $this->id)
                ->whereIn('status', ['initiated', 'pending'])
                // Dipakai "expired" karena kolom status memang tidak punya
                // nilai "cancelled" — artinya sama: pembayaran ini sudah
                // tidak berlaku lagi.
                ->update([
                    'status' => 'expired',
                    'admin_note' => 'Dibatalkan otomatis: invoice sudah lunas lewat ' . $this->reference . '.',
                ]);
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
