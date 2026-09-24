<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AffiliateReferral extends Model
{
    protected $fillable = ['affiliate_id', 'affiliate_click_id', 'client_id', 'status', 'converted_at'];

    protected function casts(): array
    {
        return ['converted_at' => 'datetime'];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function click(): BelongsTo
    {
        return $this->belongsTo(AffiliateClick::class, 'affiliate_click_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function conversion(): HasOne
    {
        return $this->hasOne(AffiliateConversion::class);
    }
}
