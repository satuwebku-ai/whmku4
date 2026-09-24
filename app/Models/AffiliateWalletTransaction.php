<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateWalletTransaction extends Model
{
    protected $fillable = [
        'affiliate_wallet_id', 'affiliate_id', 'type', 'amount', 'balance_after',
        'description', 'affiliate_commission_id', 'affiliate_payout_id', 'admin_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(AffiliateWallet::class, 'affiliate_wallet_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(AffiliateCommission::class, 'affiliate_commission_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    public function commissionReversal(): BelongsTo
    {
        return $this->belongsTo(AffiliateCommissionReversal::class, 'affiliate_commission_reversal_id');
    }
}
