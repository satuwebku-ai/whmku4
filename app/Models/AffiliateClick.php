<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AffiliateClick extends Model
{
    protected $fillable = [
        'affiliate_id', 'affiliate_campaign_id', 'cookie_token',
        'ip_address', 'user_agent', 'device_fingerprint', 'landing_url',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AffiliateCampaign::class, 'affiliate_campaign_id');
    }

    public function referral(): HasOne
    {
        return $this->hasOne(AffiliateReferral::class, 'affiliate_click_id');
    }
}
