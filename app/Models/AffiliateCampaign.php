<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateCampaign extends Model
{
    protected $fillable = [
        'affiliate_id', 'name', 'code', 'description', 'landing_url',
        'commission_type', 'commission_value', 'start_at', 'end_at',
        'clicks_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'commission_value' => 'decimal:2',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }
}
