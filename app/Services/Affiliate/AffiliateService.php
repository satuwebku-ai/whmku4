<?php

namespace App\Services\Affiliate;

use App\Exceptions\Affiliate\AffiliateException;
use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\Client;

/**
 * Pendaftaran & moderasi akun affiliate. Dipakai Client\AffiliateController
 * (daftar) dan Admin\Affiliate\AffiliateController (approve/reject).
 */
class AffiliateService
{
    public function __construct(private readonly AffiliateAuditService $audit) {}

    /**
     * Client mendaftar jadi affiliate. Satu client cuma boleh punya satu
     * profil affiliate (kolom client_id unique di migration) -- kalau
     * sudah pernah daftar, kembalikan yang lama alih-alih bikin baru.
     */
    public function register(Client $client): Affiliate
    {
        $existing = Affiliate::where('client_id', $client->id)->first();

        if ($existing) {
            return $existing;
        }

        return Affiliate::create([
            'client_id' => $client->id,
            'code' => Affiliate::generateUniqueCode(),
            'status' => 'pending',
        ]);
    }

    public function approve(Affiliate $affiliate, Admin $admin): Affiliate
    {
        if ($affiliate->status === 'approved') {
            throw new AffiliateException('Affiliate ini sudah disetujui.');
        }

        $old = $affiliate->only(['status', 'approved_by', 'approved_at', 'rejected_reason']);
        $affiliate->update([
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'rejected_reason' => null,
        ]);
        $this->audit->record('affiliate_approved', $affiliate, $admin, $old, $affiliate->only(array_keys($old)));

        return $affiliate;
    }

    public function reject(Affiliate $affiliate, string $reason, ?Admin $admin = null): Affiliate
    {
        $old = $affiliate->only(['status', 'rejected_reason']);
        $affiliate->update([
            'status' => 'rejected',
            'rejected_reason' => $reason,
        ]);
        $this->audit->record('affiliate_rejected', $affiliate, $admin, $old, $affiliate->only(array_keys($old)), $reason);

        return $affiliate;
    }

    public function suspend(Affiliate $affiliate, ?Admin $admin = null): Affiliate
    {
        $old = $affiliate->only(['status']);
        $affiliate->update(['status' => 'suspended']);
        $this->audit->record('affiliate_suspended', $affiliate, $admin, $old, $affiliate->only(array_keys($old)));

        return $affiliate;
    }
}
