<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use App\Enums\OrderStatus;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number', 'client_id', 'product_id', 'hosting_account_id',
        'product_name', 'order_type', 'amount', 'status', 'stock_reservation_status', 'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => OrderStatus::class,
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
        return 'ORD-' . NumberSequence::next('orders', 1001);
    }


    /**
     * Allowed lifecycle transitions for an order. Financial state is kept
     * separate from service fulfillment state: payment does not make an
     * order completed by itself.
     */
    private const TRANSITIONS = [
        OrderStatus::Draft->value => [OrderStatus::RequirementsPending->value, OrderStatus::PendingPayment->value, OrderStatus::Cancelled->value, OrderStatus::Expired->value],
        OrderStatus::RequirementsPending->value => [OrderStatus::RequirementsReview->value, OrderStatus::Cancelled->value, OrderStatus::Expired->value],
        OrderStatus::RequirementsReview->value => [OrderStatus::RequirementsApproved->value, OrderStatus::RequirementsRejected->value, OrderStatus::Cancelled->value],
        OrderStatus::RequirementsRejected->value => [OrderStatus::RequirementsPending->value, OrderStatus::Cancelled->value],
        OrderStatus::RequirementsApproved->value => [OrderStatus::PendingPayment->value, OrderStatus::Cancelled->value],
        OrderStatus::LegacyPending->value => [OrderStatus::PendingPayment->value, OrderStatus::Cancelled->value, OrderStatus::Expired->value],
        OrderStatus::PendingPayment->value => [OrderStatus::Paid->value, OrderStatus::Cancelled->value, OrderStatus::Expired->value],
        OrderStatus::Paid->value => [OrderStatus::Provisioning->value, OrderStatus::Completed->value, OrderStatus::Failed->value],
        OrderStatus::Provisioning->value => [OrderStatus::Completed->value, OrderStatus::Failed->value],
        OrderStatus::Failed->value => [OrderStatus::Provisioning->value, OrderStatus::Cancelled->value],
        OrderStatus::Completed->value => [],
        OrderStatus::Cancelled->value => [],
        OrderStatus::Expired->value => [],
    ];

    public function transitionTo(OrderStatus|string $to, ?string $note = null, ?int $adminId = null): bool
    {
        $target = $to instanceof OrderStatus ? $to : OrderStatus::from($to);
        $current = $this->status instanceof OrderStatus
            ? $this->status
            : OrderStatus::from((string) $this->status);

        if ($current === $target) {
            return false;
        }

        if (! in_array($target->value, self::TRANSITIONS[$current->value] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Order {$this->order_number} tidak dapat berpindah dari {$current->value} ke {$target->value}.",
            ]);
        }

        $this->status = $target;
        if ($target === OrderStatus::Provisioning && ! $this->provisioning_started_at) {
            $this->provisioning_started_at = now();
        }
        if ($target === OrderStatus::Completed) {
            $this->completed_at = now();
            $this->failed_at = null;
            $this->failure_message = null;
        }
        if ($target === OrderStatus::Failed) {
            $this->failed_at = now();
        }
        $this->save();

        $this->statusLogs()->create([
            'admin_id' => $adminId,
            'from_status' => $current->value,
            'to_status' => $target->value,
            'note' => $note,
        ]);

        return true;
    }

    public function markPendingPayment(?string $note = null): bool
    { return $this->transitionTo(OrderStatus::PendingPayment, $note); }

    public function markPaid(?string $note = null): bool
    { return $this->transitionTo(OrderStatus::Paid, $note); }

    public function markProvisioning(?string $note = null): bool
    { return $this->transitionTo(OrderStatus::Provisioning, $note); }

    public function markCompleted(?string $note = null): bool
    { return $this->transitionTo(OrderStatus::Completed, $note); }

    public function markFailed(?string $message = null): bool
    {
        $this->failure_message = $message;
        return $this->transitionTo(OrderStatus::Failed, $message);
    }

    public function cancel(?string $note = null, ?int $adminId = null): bool
    { return $this->transitionTo(OrderStatus::Cancelled, $note, $adminId); }

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

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    /**
     * Domain yang dibuat dari order ini — sisi kebalikan dari
     * Domain::order() (belongsTo lewat domains.order_id, sudah ada
     * sejak Fase 4). Order TIDAK punya kolom domain_id sendiri.
     */
    public function domain(): HasOne
    {
        return $this->hasOne(Domain::class)->latestOfMany();
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany();
    }

    /**
     * Baris invoice_items yang menagihkan order ini — dipakai order hasil
     * checkout keranjang (Fase 7c), di mana satu invoice bisa menagih
     * beberapa order sekaligus lewat invoice_items, bukan lewat
     * invoices.order_id langsung.
     */
    public function invoiceItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class)->latestOfMany();
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
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
