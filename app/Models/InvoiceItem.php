<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class InvoiceItem extends Model
{
    public const SERVICE_DOMAIN = 'domain';
    public const SERVICE_HOSTING = 'hosting';

    public const TYPE_ORDER = 'order';
    public const TYPE_RENEWAL = 'renewal';
    public const TYPE_PRIVACY = 'privacy';
    public const TYPE_UPGRADE = 'upgrade';
    public const TYPE_ADDON = 'addon';
    public const TYPE_ADDON_RENEWAL = 'addon_renewal';
    public const TYPE_OPTION_RENEWAL = 'option_renewal';

    private static ?bool $hasServiceColumns = null;

    protected $fillable = [
        'invoice_id', 'order_id', 'description', 'amount',
        'service_type', 'service_id', 'item_type',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Kolom rujukan layanan baru ada setelah migrasi dijalankan. Kode yang
     * menulis/membaca kolom itu wajib bertanya ke sini dulu, supaya deploy
     * kode sebelum `php artisan migrate` tidak merusak pembuatan invoice.
     */
    public static function supportsServiceRef(): bool
    {
        return self::$hasServiceColumns ??= Schema::hasColumn('invoice_items', 'service_type');
    }

    /**
     * Atribut rujukan layanan untuk InvoiceItem::create([... , ...InvoiceItem::serviceRef($x, 'renewal')]).
     * Mengembalikan array kosong bila kolom belum ada.
     *
     * @return array{service_type?: string, service_id?: int, item_type?: string}
     */
    public static function serviceRef(Model $service, string $itemType): array
    {
        if (! self::supportsServiceRef()) {
            return [];
        }

        $type = match (true) {
            $service instanceof Domain => self::SERVICE_DOMAIN,
            $service instanceof HostingAccount => self::SERVICE_HOSTING,
            default => throw new \InvalidArgumentException('Layanan tidak dikenal: ' . $service::class),
        };

        return [
            'service_type' => $type,
            'service_id' => (int) $service->getKey(),
            'item_type' => $itemType,
        ];
    }

    /** Layanan yang dirujuk (null bila tidak ada atau sudah dihapus). */
    public function service(): ?Model
    {
        if (! self::supportsServiceRef() || ! $this->service_id) {
            return null;
        }

        return match ($this->service_type) {
            self::SERVICE_DOMAIN => Domain::find($this->service_id),
            self::SERVICE_HOSTING => HostingAccount::find($this->service_id),
            default => null,
        };
    }

    public function serviceLabel(): string
    {
        $isDomain = $this->service_type === self::SERVICE_DOMAIN;

        return match ($this->item_type) {
            self::TYPE_ORDER => $isDomain ? 'Pesanan domain' : 'Pesanan hosting',
            self::TYPE_RENEWAL => $isDomain ? 'Perpanjangan domain' : 'Perpanjangan hosting',
            self::TYPE_PRIVACY => 'ID Protection',
            self::TYPE_UPGRADE => 'Upgrade hosting',
            self::TYPE_ADDON => 'Addon hosting',
            self::TYPE_ADDON_RENEWAL => 'Perpanjangan addon',
            self::TYPE_OPTION_RENEWAL => 'Opsi hosting',
            default => $isDomain ? 'Domain' : 'Hosting',
        };
    }
}
