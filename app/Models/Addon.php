<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addon extends Model
{
    protected $fillable = [
        'name', 'slug', 'description',
        'price_monthly', 'price_quarterly', 'price_semi_annually', 'price_annually',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_quarterly' => 'decimal:2',
            'price_semi_annually' => 'decimal:2',
            'price_annually' => 'decimal:2',
            'is_active' => 'boolean',
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
