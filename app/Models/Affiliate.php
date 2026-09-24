<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Affiliate extends Model
{
    protected $fillable = [
        'client_id', 'code', 'status', 'commission_type', 'commission_value',
        'bank_name', 'bank_account_number', 'bank_account_name',
        'affiliate_type', 'company_name', 'tax_number', 'phone', 'address',
        'city', 'province', 'country', 'payment_method',
        'approved_by', 'approved_at', 'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'commission_value' => 'decimal:2',
        ];
    }

    /**
     * Kode referral unik 8 karakter, dicek tabrakan sampai benar-benar
     * belum dipakai -- dipanggil AffiliateService::register(), bukan
     * event model, supaya pemanggilnya bisa langsung dapat kodenya untuk
     * ditampilkan tanpa reload.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AffiliateCampaign::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(AffiliateWallet::class);
    }

    public function fraudFlags(): HasMany
    {
        return $this->hasMany(AffiliateFraudFlag::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AffiliateAuditLog::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
