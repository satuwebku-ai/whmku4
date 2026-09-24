<?php

namespace App\Services\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateConversion;
use App\Models\AffiliateFraudFlag;
use App\Models\AffiliateReferral;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AffiliateFraudService
{
    /**
     * Signals are deliberately explainable so an admin can review why a
     * commission was held instead of relying on an opaque score.
     *
     * @return array{flagged: bool, signals: array<string, mixed>}
     */
    public function signalsForReferral(AffiliateClick $click, Client $client, Request $request): array
    {
        $signals = [];
        $affiliate = $click->affiliate;

        if ($affiliate->client_id === $client->id) {
            $signals['self_referral'] = true;
        }

        if ($click->ip_address && $click->ip_address === $request->ip()) {
            $signals['same_ip_as_registration'] = $click->ip_address;
        } elseif ($click->ip_address && $client->last_login_ip && $click->ip_address === $client->last_login_ip) {
            $signals['same_ip_as_client_login'] = $click->ip_address;
        }

        $normalizedEmail = mb_strtolower(trim((string) $client->email));
        if ($normalizedEmail && $affiliate->client?->email && mb_strtolower(trim($affiliate->client->email)) === $normalizedEmail) {
            $signals['same_email'] = true;
        }

        $phone = preg_replace('/\D+/', '', (string) ($client->phone ?: $client->whatsapp_number));
        $affiliatePhone = preg_replace('/\D+/', '', (string) ($affiliate->phone ?: $affiliate->client?->phone));
        if ($phone && $affiliatePhone && $phone === $affiliatePhone) {
            $signals['same_phone'] = true;
        }

        if ($click->device_fingerprint && AffiliateClick::where('affiliate_id', $affiliate->id)
            ->where('device_fingerprint', $click->device_fingerprint)
            ->where('id', '!=', $click->id)
            ->exists()) {
            $signals['repeated_device'] = true;
        }

        if ($click->ip_address && AffiliateClick::where('affiliate_id', $affiliate->id)
            ->where('ip_address', $click->ip_address)
            ->where('created_at', '>=', now()->subHours(24))
            ->count() > 10) {
            $signals['repeated_clicks_same_ip'] = true;
        }

        if ($request->ip() && AffiliateFraudFlag::where('affiliate_id', $affiliate->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereJsonContains('signals->ip', $request->ip())
            ->exists()) {
            $signals['known_fraud_ip'] = $request->ip();
        }

        if ($request->ip()) {
            $signals['ip'] = $request->ip();
        }

        return ['flagged' => $signals !== [], 'signals' => $signals];
    }

    public function flagReferral(AffiliateReferral $referral, array $signals): ?AffiliateFraudFlag
    {
        if ($signals === []) {
            return null;
        }

        return AffiliateFraudFlag::firstOrCreate(
            [
                'affiliate_id' => $referral->affiliate_id,
                'client_id' => $referral->client_id,
                'affiliate_referral_id' => $referral->id,
                'status' => 'review',
            ],
            [
                'reason' => implode(', ', array_keys($signals)),
                'signals' => $signals,
            ],
        );
    }

    public function flagConversion(AffiliateConversion $conversion, array $signals, string $reason = 'Fraud review'): AffiliateFraudFlag
    {
        return AffiliateFraudFlag::firstOrCreate(
            [
                'affiliate_id' => $conversion->affiliate_id,
                'client_id' => $conversion->client_id,
                'affiliate_conversion_id' => $conversion->id,
                'invoice_id' => $conversion->invoice_id,
                'status' => 'review',
            ],
            [
                'reason' => $reason,
                'signals' => $signals,
            ],
        );
    }

    public function review(AffiliateFraudFlag $flag, string $status, ?int $adminId, ?string $notes = null): AffiliateFraudFlag
    {
        abort_unless(in_array($status, ['approved', 'rejected', 'blocked'], true), 422);

        $flag->update([
            'status' => $status,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'reviewer_notes' => $notes,
        ]);

        return $flag->fresh();
    }

    public function pending(): Collection
    {
        return AffiliateFraudFlag::with(['affiliate.client', 'client', 'conversion.invoice'])
            ->where('status', 'review')
            ->latest()
            ->get();
    }
}