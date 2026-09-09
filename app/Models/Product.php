<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_category_id', 'name', 'slug', 'tagline', 'description', 'features',
        'price_monthly', 'price_quarterly', 'price_semi_annually', 'price_annually', 'price_custom', 'setup_fee',
        'custom_cycle_days', 'domain_option', 'server_id', 'panel_package', 'billing_mode',
        'is_active', 'is_featured', 'stock', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price_monthly' => 'decimal:2',
            'price_quarterly' => 'decimal:2',
            'price_semi_annually' => 'decimal:2',
            'price_annually' => 'decimal:2',
            'price_custom' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Label siklus tagihan dalam Bahasa Indonesia — dipakai berulang di
     * halaman katalog, form produk, dan keranjang.
     */
    public const CYCLES = [
        'monthly' => 'Bulanan',
        'quarterly' => '3 Bulan',
        'semi_annually' => '6 Bulan',
        'annually' => 'Tahunan',
        'custom' => 'Custom',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (blank($product->slug)) {
                $product->slug = static::uniqueSlug($product->name, $product->id);
            } else {
                $product->slug = Str::slug($product->slug);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function optionGroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Siklus tagihan yang benar-benar dijual untuk produk ini
     * (harga-nya diisi, tidak null).
     *
     * @return array<string, float>
     */
    public function availableCycles(): array
    {
        $cycles = [];

        foreach (self::CYCLES as $key => $label) {
            $price = $this->{"price_{$key}"};

            if ($price !== null) {
                $cycles[$key] = (float) $price;
            }
        }

        return $cycles;
    }

    public function priceForCycle(string $cycle): ?float
    {
        $value = $this->{"price_{$cycle}"} ?? null;

        return $value !== null ? (float) $value : null;
    }

    /**
     * Label yang ditampilkan ke klien — untuk siklus custom, tampilkan
     * jumlah harinya langsung ("Custom (45 hari)") supaya jelas, bukan
     * cuma kata "Custom" tanpa keterangan.
     */
    public function cycleLabel(string $cycle): string
    {
        if ($cycle === 'custom' && $this->custom_cycle_days) {
            return "Custom ({$this->custom_cycle_days} hari)";
        }

        return self::CYCLES[$cycle] ?? $cycle;
    }

    /**
     * Harga terendah untuk ditampilkan di kartu katalog, mis. "mulai dari".
     */
    public function getStartingPriceAttribute(): ?float
    {
        $cycles = $this->availableCycles();

        return $cycles ? min($cycles) : null;
    }

    public function requiresDomain(): bool
    {
        return $this->domain_option === 'required';
    }

    public function allowsDomain(): bool
    {
        return $this->domain_option !== 'none';
    }

    public function isInStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }
}
