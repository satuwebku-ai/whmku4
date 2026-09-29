<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addon extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'brand', 'summary', 'description', 'long_description',
        'features', 'specs', 'faqs',
        'price_monthly', 'price_quarterly', 'price_semi_annually', 'price_annually',
        'cost_price_monthly', 'cost_price_quarterly', 'cost_price_semi_annually', 'cost_price_annually',
        'pricing_source', 'is_public', 'supplier_api_url', 'supplier_http_method', 'supplier_api_token',
        'supplier_price_path_monthly', 'supplier_price_path_quarterly',
        'supplier_price_path_semi_annually', 'supplier_price_path_annually',
        'supplier_last_synced_at', 'supplier_last_error',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_quarterly' => 'decimal:2',
            'price_semi_annually' => 'decimal:2',
            'price_annually' => 'decimal:2',
            'cost_price_monthly' => 'decimal:2',
            'cost_price_quarterly' => 'decimal:2',
            'cost_price_semi_annually' => 'decimal:2',
            'cost_price_annually' => 'decimal:2',
            'features' => 'array',
            'specs' => 'array',
            'faqs' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'supplier_api_token' => 'encrypted',
            'supplier_last_synced_at' => 'datetime',
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HostingAccountAddon::class);
    }

    public function priceForCycle(string $cycle): ?float
    {
        $value = $this->{"price_{$cycle}"} ?? null;

        return $value !== null ? (float) $value : null;
    }

    public function costPriceForCycle(string $cycle): ?float
    {
        $value = $this->{"cost_price_{$cycle}"} ?? null;

        return $value !== null ? (float) $value : null;
    }

    public function availableCycles(): array
    {
        return collect(['monthly', 'quarterly', 'semi_annually', 'annually'])
            ->mapWithKeys(fn (string $cycle) => [$cycle => $this->priceForCycle($cycle)])
            ->filter(fn ($price) => $price !== null)
            ->all();
    }

    public function isApiPricing(): bool
    {
        return $this->pricing_source === 'api';
    }

    public const CYCLE_LABELS = ['monthly' => 'Bulanan', 'quarterly' => '3 Bulan', 'semi_annually' => '6 Bulan', 'annually' => 'Tahunan'];

    public const CYCLE_SUFFIX = ['monthly' => '/bulan', 'quarterly' => '/3 bulan', 'semi_annually' => '/6 bulan', 'annually' => '/tahun'];

    public const CATEGORIES = [
        'ssl' => 'Sertifikat SSL',
        'license' => 'Lisensi Software',
    ];

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Lisensi';
    }

    /** Siklus termurah, dipakai untuk label "mulai dari" & tombol Tambah ke Keranjang di katalog. */
    public function cheapestCycle(): ?array
    {
        $cycles = $this->availableCycles();
        if ($cycles === []) {
            return null;
        }
        asort($cycles);

        return ['cycle' => array_key_first($cycles), 'price' => reset($cycles)];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
