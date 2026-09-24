<?php

namespace App\Services\Affiliate;

use App\Models\Affiliate;

/**
 * Agregat statistik untuk dashboard Client\AffiliateController dan
 * laporan Admin\Affiliate\ReportController. Sengaja query langsung (bukan
 * cache) -- volume data affiliate untuk satu pemilik bisnis hosting skala
 * kecil-menengah masih jauh dari perlu dicache.
 */
class AffiliateReportService
{
    /**
     * @return array<string, int|float>
     */
    public function summaryFor(Affiliate $affiliate): array
    {
        $clicks = $affiliate->clicks()->count();
        $conversions = $affiliate->conversions()->count();

        return [
            'clicks' => $clicks,
            'referrals' => $affiliate->referrals()->count(),
            'conversions' => $conversions,
            'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0,
            'commission_pending' => (float) $affiliate->commissions()->where('status', 'pending')->sum('amount'),
            'commission_approved' => (float) $affiliate->commissions()->where('status', 'approved')->sum('amount'),
            'commission_cancelled' => (float) $affiliate->commissions()->where('status', 'cancelled')->sum('amount'),
            'revenue' => (float) $affiliate->conversions()->sum('net_paid_amount'),
            'total_withdrawn' => (float) $affiliate->payouts()->where('status', 'paid')->sum('amount'),
            'wallet_balance' => (float) ($affiliate->wallet?->balance ?? 0),
        ];
    }

    /**
     * Ringkasan seluruh program untuk dashboard admin: total affiliate
     * per status, total komisi yang sudah disetujui (=biaya program
     * sejauh ini), dan payout yang masih menunggu diproses.
     *
     * @return array{affiliates_pending: int, affiliates_approved: int, commission_approved_total: float, payouts_pending: int, payouts_pending_amount: float}
     */
    public function programSummary(): array
    {
        return [
            'affiliates_pending' => Affiliate::where('status', 'pending')->count(),
            'affiliates_approved' => Affiliate::where('status', 'approved')->count(),
            'commission_approved_total' => (float) \App\Models\AffiliateCommission::where('status', 'approved')->sum('amount'),
            'payouts_pending' => \App\Models\AffiliatePayout::where('status', 'pending')->count(),
            'payouts_pending_amount' => (float) \App\Models\AffiliatePayout::where('status', 'pending')->sum('amount'),
            'fraud_review' => \App\Models\AffiliateFraudFlag::where('status', 'review')->count(),
        ];
    }
}
