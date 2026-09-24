<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AffiliateCommission extends Model
{
    protected $fillable = [
        'affiliate_id', 'affiliate_conversion_id', 'amount', 'status',
        'commission_type', 'commission_rate', 'commission_base', 'tax_amount',
        'net_amount', 'event_type', 'approved_by', 'approved_at',
        'cancelled_reason', 'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'commission_base' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(AffiliateConversion::class, 'affiliate_conversion_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function walletTransaction(): HasOne
    {
        return $this->hasOne(AffiliateWalletTransaction::class, 'affiliate_commission_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(AffiliateCommissionReversal::class);
    }
}
