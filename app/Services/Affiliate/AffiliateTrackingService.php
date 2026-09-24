<?php

namespace App\Services\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateCampaign;
use App\Models\AffiliateClick;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Menangani kunjungan lewat link referral (`/ref/{code}` atau
 * `?ref={code}`). Mencatat AffiliateClick, lalu menitipkan token klik itu
 * di cookie browser pengunjung supaya kalau ia baru mendaftar BEBERAPA
 * HARI kemudian, AffiliateReferralService masih bisa tahu klik mana yang
 * membawanya ke sini.
 */
class AffiliateTrackingService
{
    private const COOKIE_NAME = 'affiliate_ref';

    /**
     * Cari affiliate dari kode referral, catat klik-nya, kembalikan
     * cookie yang harus ditempelkan ke response (dipanggil dari
     * controller karena Cookie harus di-attach ke response, bukan dibuat
     * di service).
     *
     * @return array{click: ?AffiliateClick, cookie: ?Cookie}
     */
    public function handleVisit(Request $request, string $code, ?string $campaignCode = null): array
    {
        $affiliate = Affiliate::where('code', $code)->where('status', 'approved')->first();

        if (! $affiliate) {
            return ['click' => null, 'cookie' => null];
        }

        $campaign = $campaignCode
            ? AffiliateCampaign::where('affiliate_id', $affiliate->id)
                ->where('code', $campaignCode)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('start_at')->orWhere('start_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('end_at')->orWhere('end_at', '>=', now());
                })
                ->first()
            : null;

        $token = (string) Str::uuid();
        $existingToken = $request->cookie(self::COOKIE_NAME);
        $attributionModel = Setting::get('affiliate_attribution_model', 'first_click');

        $click = AffiliateClick::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_campaign_id' => $campaign?->id,
            'cookie_token' => $token,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'device_fingerprint' => hash('sha256', implode('|', [
                (string) $request->ip(),
                (string) $request->userAgent(),
                (string) $request->header('accept-language'),
            ])),
            'landing_url' => $request->fullUrl(),
        ]);

        if ($campaign) {
            $campaign->increment('clicks_count');
        }

        $cookieToken = $attributionModel === 'first_click' && $existingToken
            && AffiliateClick::where('cookie_token', $existingToken)->exists()
            ? $existingToken
            : $token;
        $cookieDays = max(1, (int) Setting::get('affiliate_cookie_days', 30));
        $cookie = cookie(self::COOKIE_NAME, $cookieToken, $cookieDays * 24 * 60);

        return ['click' => $click, 'cookie' => $cookie];
    }

    /**
     * Ambil AffiliateClick yang cocok dengan cookie di request saat ini
     * (kalau ada) -- dipakai AffiliateReferralService saat registrasi.
     */
    public function pendingClick(Request $request): ?AffiliateClick
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (! $token) {
            return null;
        }

        return AffiliateClick::where('cookie_token', $token)->first();
    }

    public function cookieName(): string
    {
        return self::COOKIE_NAME;
    }
}
