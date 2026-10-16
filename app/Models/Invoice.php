<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use Throwable;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

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

        // Soft delete tidak memicu FK nullOnDelete (barisnya masih ada),
        // jadi tautan di domain harus dilepas manual.
        static::deleted(function (Invoice $invoice) {
            \App\Models\Domain::where('privacy_invoice_id', $invoice->id)
                ->update(['privacy_invoice_id' => null]);

            static::releasePendingHostingAddons($invoice);
        });

        static::updated(function (Invoice $invoice) {
            // Invoice ID Protection yang dibatalkan lewat jalur mana pun
            // (admin, cron, edit langsung) harus melepas kaitannya di
            // domain; kalau tidak, halaman Addons terus menampilkan
            // "Bayar Sekarang" ke invoice yang sudah mati dan klien tidak
            // bisa membuat invoice baru.
            if ($invoice->wasChanged('status') && $invoice->status === 'cancelled') {
                \App\Models\Domain::where('privacy_invoice_id', $invoice->id)
                    ->update(['privacy_invoice_id' => null]);

                static::releasePendingHostingAddons($invoice);
            }

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

    /**
     * Addon hosting yang masih menunggu bayar invoice ini dilepas saat
     * invoice dibatalkan/dihapus. Tanpa ini barisnya macet di
     * pending_payment dengan addon_id terisi: klien tidak bisa memesan
     * ulang (unique key + cek "sudah terpasang atau menunggu pembayaran")
     * dan addon-nya hilang dari daftar yang tersedia. addon_id dikosongkan
     * mengikuti pola cancelAddon(); nama & harga tetap jadi snapshot.
     */
    protected static function releasePendingHostingAddons(Invoice $invoice): void
    {
        \App\Models\HostingAccountAddon::where('invoice_id', $invoice->id)
            ->where('status', 'pending_payment')
            ->update(['status' => 'cancelled', 'addon_id' => null]);
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

    /**
     * Domain di invoice ini yang berkas persyaratannya belum lengkap/disetujui
     * (null kalau tidak ada). Hanya relevan untuk invoice yang masih bisa
     * dibayar. Memakai gerbang yang sama dengan PaymentEligibilityService,
     * jadi tampilan klien dan penolakan server tidak bisa berbeda pendapat.
     */
    public function documentBlocker(): ?Domain
    {
        if ($this->is_topup || ! in_array($this->status, ['unpaid', 'overdue'], true)) {
            return null;
        }

        return app(\App\Services\Payment\PaymentEligibilityService::class)->documentBlocker($this);
    }

    /**
     * Layanan yang ditagih invoice ini (domain / hosting).
     *
     * Sumber utama: rujukan di baris invoice (invoice_items.service_type /
     * service_id) — permanen, jadi invoice yang sudah lunas pun tetap tahu
     * layanannya. Cadangan untuk data lama yang belum terisi: tautan
     * sementara di layanan (renewal_invoice_id, privacy_invoice_id, dst).
     *
     * @return \Illuminate\Support\Collection<int, array{label: string, kind: string, name: string, route: string, model: \Illuminate\Database\Eloquent\Model}>
     */
    public function relatedServices(): \Illuminate\Support\Collection
    {
        $rows = collect();
        $seen = [];

        $push = function (string $label, string $kind, \Illuminate\Database\Eloquent\Model $model) use ($rows, &$seen): void {
            $key = $kind . ':' . $model->getKey() . ':' . $label;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;

            $rows->push([
                'label' => $label,
                'kind' => $kind,
                'name' => $kind === 'domain' ? $model->domain_name : $model->domain,
                'route' => $kind === 'domain'
                    ? route('admin.domains.details', $model)
                    : route('admin.hosting-accounts.details', $model),
                'model' => $model,
            ]);
        };

        if (InvoiceItem::supportsServiceRef()) {
            foreach ($this->items()->whereNotNull('service_type')->get() as $item) {
                if ($service = $item->service()) {
                    $push($item->serviceLabel(), $item->service_type, $service);
                }
            }
        }

        // Cadangan untuk invoice lama yang belum di-backfill.
        Domain::where('renewal_invoice_id', $this->id)->get()
            ->each(fn (Domain $d) => $push('Perpanjangan domain', 'domain', $d));

        Domain::where('privacy_invoice_id', $this->id)->get()
            ->each(fn (Domain $d) => $push('ID Protection', 'domain', $d));

        HostingAccount::where('renewal_invoice_id', $this->id)
            ->orWhere('pending_upgrade_invoice_id', $this->id)
            ->get()
            ->each(fn (HostingAccount $h) => $push(
                (int) $h->renewal_invoice_id === (int) $this->id ? 'Perpanjangan hosting' : 'Upgrade hosting',
                'hosting',
                $h,
            ));

        return $rows;
    }

    /**
     * Invoice yang dibuat sistem (checkout, renewal, privacy, upgrade) tidak
     * boleh diubah tautan order/nominalnya lewat form edit admin. Order pada
     * invoice menentukan provisioning & status order saat dibayar.
     */
    public function isSystemGenerated(): bool
    {
        return $this->order_id !== null
            || $this->items()->exists()
            || $this->relatedServices()->isNotEmpty();
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'unpaid' && $this->due_date?->isPast();
    }
}
