<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateFraudFlag extends Model
{
    protected $fillable = [
        'affiliate_id', 'client_id', 'affiliate_referral_id',
        'affiliate_conversion_id', 'invoice_id', 'status', 'reason',
        'signals', 'reviewed_by', 'reviewed_at', 'reviewer_notes',
    ];

    protected function casts(): array
    {
        return [
            'signals' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo { return $this->belongsTo(Affiliate::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function referral(): BelongsTo { return $this->belongsTo(AffiliateReferral::class, 'affiliate_referral_id'); }
    public function conversion(): BelongsTo { return $this->belongsTo(AffiliateConversion::class, 'affiliate_conversion_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(Admin::class, 'reviewed_by'); }
}