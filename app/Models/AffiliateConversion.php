<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AffiliateConversion extends Model
{
    protected $fillable = [
        'affiliate_id', 'affiliate_referral_id', 'client_id', 'invoice_id',
        'product_type', 'event_type', 'amount', 'net_paid_amount',
        'commission_window_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'net_paid_amount' => 'decimal:2',
            'commission_window_expires_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferral::class, 'affiliate_referral_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(AffiliateCommission::class);
    }

    public function fraudFlags(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AffiliateFraudFlag::class);
    }
}
