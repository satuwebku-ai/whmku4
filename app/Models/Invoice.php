<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use Throwable;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number', 'client_id', 'order_id', 'coupon_id', 'tax_id', 'tax_rate', 'amount', 'tax', 'discount', 'total',
        'status', 'issue_date', 'due_date', 'paid_at', 'payment_method', 'notes', 'is_topup',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'total' => 'decimal:2',
            'issue_date' => 'date',
            'due_date' => 'date',
            'is_topup' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            // Eloquent tidak menghidrasi nilai DEFAULT database kembali ke
            // instance setelah INSERT. Tetapkan state awal di model juga,
            // supaya operasi langsung setelah create() (mis. markOverdue)
            // tidak melihat status null.
            $invoice->status ??= 'unpaid';

            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = static::generateInvoiceNumber();
            }

            $invoice->recalculateTotal();
        });

        static::updating(function (Invoice $invoice) {
            if ($invoice->isDirty(['amount', 'tax', 'discount'])) {
                $invoice->recalculateTotal();
            }
        });

        // Invoice baru terbit → kirim ke email klien.
        static::created(function (Invoice $invoice) {
            try {
                app(\App\Services\Notification\NotificationService::class)->invoiceCreated($invoice);
            } catch (Throwable $e) {
                Log::warning('Notifikasi invoice baru gagal: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
            }
        });

        static::updated(function (Invoice $invoice) {
            if ($invoice->wasChanged('status') && in_array($invoice->status, ['overdue', 'cancelled'], true)) {
                try {
                    app(\App\Services\Billing\CouponService::class)->releaseForInvoice($invoice);
                    if (! $invoice->is_topup) {
                        app(\App\Services\Provisioning\ProvisioningService::class)->releaseCheckoutReservations($invoice);
                    }
                } catch (Throwable $e) {
                    Log::error('Gagal melepas reservation checkout: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
                }
            }
        });
    }

    public static function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $next = NumberSequence::next('invoices:' . $year, 1);

        return "INV-{$year}-" . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }


    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function recalculateTotal(): void
    {
        $result = app(\App\Services\Billing\InvoiceCalculationService::class)->calculate(
            $this->amount ?? 0,
            $this->tax ?? 0,
            $this->discount ?? 0,
            $this->tax_rate !== null ? (string) $this->tax_rate : null,
        );

        $this->total = $result['total'];
    }

    public function recalculateFromItems(): self
    {
        $amount = $this->items()->sum('amount');
        $this->amount = $amount;
        $this->recalculateTotal();
        $this->save();

        return $this->refresh();
    }

    public function markOverdue(): bool
    {
        if ($this->status !== 'unpaid' || ! $this->due_date?->isPast()) {
            return false;
        }

        return $this->update(['status' => 'overdue']);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponUsage(): HasOne
    {
        return $this->hasOne(CouponUsage::class);
    }

    /**
     * Rincian per item — dipakai invoice hasil checkout keranjang yang bisa
     * berisi beberapa order sekaligus. Invoice manual lama (Fase 2) tidak
     * punya baris di sini dan tetap ditampilkan lewat relasi order() tunggal.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'unpaid' && $this->due_date?->isPast();
    }
}
