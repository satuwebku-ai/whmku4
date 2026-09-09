<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number', 'client_id', 'product_id', 'hosting_account_id',
        'product_name', 'order_type', 'amount', 'status', 'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $last = static::orderByDesc('id')->first();
        $next = $last ? ((int) Str::afterLast($last->order_number, '-') + 1) : 1001;

        return 'ORD-' . $next;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function hostingAccount(): BelongsTo
    {
        return $this->belongsTo(HostingAccount::class);
    }

    /**
     * Domain yang dibuat dari order ini — sisi kebalikan dari
     * Domain::order() (belongsTo lewat domains.order_id, sudah ada
     * sejak Fase 4). Order TIDAK punya kolom domain_id sendiri.
     */
    public function domain(): HasOne
    {
        return $this->hasOne(Domain::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Baris invoice_items yang menagihkan order ini — dipakai order hasil
     * checkout keranjang (Fase 7c), di mana satu invoice bisa menagih
     * beberapa order sekaligus lewat invoice_items, bukan lewat
     * invoices.order_id langsung.
     */
    public function invoiceItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class);
    }

    /**
     * Invoice yang menagih order ini, dari jalur manapun — invoice manual
     * lama (invoices.order_id) ATAU invoice hasil checkout (invoice_items).
     * Pakai ini di view, bukan invoice()/invoiceItem() langsung, supaya
     * tidak perlu tahu order ini dibuat lewat jalur mana.
     */
    public function resolvedInvoice(): ?Invoice
    {
        return $this->invoice ?? $this->invoiceItem?->invoice;
    }
}
