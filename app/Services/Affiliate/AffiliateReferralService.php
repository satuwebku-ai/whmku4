<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateReferral;
use App\Models\Client;
use Illuminate\Http\Request;

/**
 * Dipanggil SEKALI dari Auth\Client\RegisterController::store() setelah
 * Client baru dibuat -- kalau ada cookie affiliate_ref yang cocok dengan
 * klik yang tercatat, buat baris atribusi di affiliate_referrals.
 *
 * Client yang mendaftar TANPA cookie referral (paling banyak kasusnya)
 * tidak menghasilkan apa-apa di sini -- itu normal, bukan kesalahan.
 */
class AffiliateReferralService
{
    public function __construct(
        private readonly AffiliateTrackingService $tracking,
        private readonly AffiliateFraudService $fraud,
    ) {}

    public function attachToNewClient(Client $client, Request $request): ?AffiliateReferral
    {
        $click = $this->tracking->pendingClick($request);

        if (! $click) {
            return null;
        }

        // Affiliate tidak boleh mereferensikan dirinya sendiri.
        if ($click->affiliate->client_id === $client->id) {
            return null;
        }

        // client_id unique di migration -- kalau karena suatu sebab
        // client ini sudah punya referral (semestinya tidak mungkin untuk
        // client yang baru saja dibuat), jangan buat duplikat.
        if (AffiliateReferral::where('client_id', $client->id)->exists()) {
            return null;
        }

        $referral = AffiliateReferral::create([
            'affiliate_id' => $click->affiliate_id,
            'affiliate_click_id' => $click->id,
            'client_id' => $client->id,
            'status' => 'pending',
        ]);

        $signals = $this->fraud->signalsForReferral($click, $client, $request);
        $this->fraud->flagReferral($referral, $signals['signals']);

        return $referral;
    }
}
