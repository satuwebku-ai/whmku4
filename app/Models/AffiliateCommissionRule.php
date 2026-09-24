<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateCommissionRule extends Model
{
    protected $fillable = [
        'name', 'product_type', 'event_type', 'commission_type',
        'commission_value', 'duration_days', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'commission_value' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}