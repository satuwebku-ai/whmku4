<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AffiliateCommissionReversal extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'affiliate_commission_id', 'affiliate_id', 'amount', 'reason', 'admin_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function commission(): BelongsTo { return $this->belongsTo(AffiliateCommission::class); }
    public function affiliate(): BelongsTo { return $this->belongsTo(Affiliate::class); }
    public function admin(): BelongsTo { return $this->belongsTo(Admin::class); }
    public function walletTransaction(): HasOne
    {
        return $this->hasOne(AffiliateWalletTransaction::class, 'affiliate_commission_reversal_id');
    }
}