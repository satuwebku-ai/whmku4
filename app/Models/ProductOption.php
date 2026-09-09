<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOption extends Model
{
    protected $fillable = [
        'product_option_group_id', 'name',
        'price_monthly', 'price_quarterly', 'price_semi_annually', 'price_annually', 'price_custom',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_quarterly' => 'decimal:2',
            'price_semi_annually' => 'decimal:2',
            'price_annually' => 'decimal:2',
            'price_custom' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'product_option_group_id');
    }

    /**
     * Harga opsi ini untuk satu siklus tagihan tertentu — NULL berarti
     * opsi ini tidak dijual untuk siklus itu (dilewati saat checkout,
     * bukan dianggap gratis).
     */
    public function priceForCycle(string $cycle): ?float
    {
        $value = $this->{"price_{$cycle}"} ?? null;

        return $value !== null ? (float) $value : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
