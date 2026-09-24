<?php

namespace App\Services\Affiliate;

use App\Exceptions\Affiliate\AffiliateException;
use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class AffiliatePayoutService
{
    public function __construct(
        private readonly AffiliateWalletService $wallet,
        private readonly AffiliateAuditService $audit,
    ) {}

    public function request(Affiliate $affiliate, float $amount): AffiliatePayout
    {
        $minimum = (float) Setting::get('affiliate_min_payout', 50000);
        if ($amount < $minimum) {
            throw new AffiliateException('Minimal payout Rp ' . number_format($minimum, 0, ',', '.') . '.');
        }

        if (! $affiliate->bank_account_number) {
            throw new AffiliateException('Lengkapi data rekening bank di profil affiliate Anda terlebih dahulu.');
        }

        // Lock wallet + buat payout pending dalam TRANSACTION yang sama.
        // Dengan begitu dua request simultan tidak dapat sama-sama memesan
        // saldo yang sama sebelum baris pending sempat tercatat.
        return DB::transaction(function () use ($affiliate, $amount) {
            $wallet = $this->wallet->walletFor($affiliate);
            $wallet = $wallet->newQuery()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $pending = (float) AffiliatePayout::where('affiliate_id', $affiliate->id)
                ->where('status', 'pending')->sum('amount');
            $available = (float) $wallet->balance - $pending;

            if ($available < $amount) {
                throw new AffiliateException('Saldo tersedia setelah memperhitungkan payout pending tidak cukup.');
            }

            return AffiliatePayout::create([
                'affiliate_id' => $affiliate->id,
                'amount' => $amount,
                'bank_name' => $affiliate->bank_name,
                'bank_account_number' => $affiliate->bank_account_number,
                'bank_account_name' => $affiliate->bank_account_name,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Baris payout dikunci lalu status dicek ULANG memakai baris yang
     * sudah terkunci itu -- bukan status dari $payout yang mungkin sudah
     * basi. Tanpa ini, dua klik "Setujui" yang datang nyaris bersamaan
     * (atau dua tab admin) bisa sama-sama lolos cek status=pending
     * sebelum salah satunya sempat menulis, lalu wallet affiliate
     * terpotong DUA KALI untuk satu payout yang sama.
     */
    public function approve(AffiliatePayout $payout, Admin $admin): AffiliatePayout
    {
        return DB::transaction(function () use ($payout, $admin) {
            $locked = AffiliatePayout::query()->lockForUpdate()->findOrFail($payout->id);

            if ($locked->status !== 'pending') {
                throw new AffiliateException('Payout ini sudah diproses sebelumnya.');
            }

            // Baru di sini saldo benar-benar dipotong -- lihat catatan di
            // request() di atas kenapa bukan saat request dibuat. Kalau
            // saldo ternyata tidak cukup, exception dari sini membatalkan
            // seluruh transaksi -- status payout TIDAK ikut berubah.
            $this->wallet->debitFromPayout($locked);

            $locked->update([
                'status' => 'approved',
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);
            $this->audit->record('payout_approved', $locked->affiliate, $admin, ['status' => 'pending'], ['status' => 'approved']);

            return $locked;
        });
    }

    public function startProcessing(AffiliatePayout $payout, Admin $admin): AffiliatePayout
    {
        return DB::transaction(function () use ($payout, $admin) {
            $locked = AffiliatePayout::query()->lockForUpdate()->findOrFail($payout->id);

            if ($locked->status !== 'approved') {
                throw new AffiliateException('Payout harus disetujui dulu sebelum diproses.');
            }

            $locked->update([
                'status' => 'processing',
                'processed_by' => $admin->id,
                'processed_at' => $locked->processed_at ?? now(),
            ]);
            $this->audit->record('payout_processing', $locked->affiliate, $admin, ['status' => 'approved'], ['status' => 'processing']);

            return $locked;
        });
    }

    public function markPaid(
        AffiliatePayout $payout,
        Admin $admin,
        ?string $transactionReference = null,
        ?string $paymentProof = null,
    ): AffiliatePayout {
        return DB::transaction(function () use ($payout, $admin, $transactionReference, $paymentProof) {
            $locked = AffiliatePayout::query()->lockForUpdate()->findOrFail($payout->id);

            if (! in_array($locked->status, ['approved', 'processing'], true)) {
                throw new AffiliateException('Payout harus berada pada status approved atau processing.');
            }

            $oldStatus = $locked->status;
            $locked->update([
                'status' => 'paid',
                'processed_by' => $admin->id,
                'processed_at' => $locked->processed_at ?? now(),
                'transaction_reference' => $transactionReference ?: $locked->transaction_reference,
                'payment_proof' => $paymentProof ?: $locked->payment_proof,
                'failure_reason' => null,
            ]);
            $this->audit->record('payout_paid', $locked->affiliate, $admin, ['status' => $oldStatus], ['status' => 'paid']);

            return $locked;
        });
    }

    public function markFailed(AffiliatePayout $payout, string $reason, Admin $admin): AffiliatePayout
    {
        return DB::transaction(function () use ($payout, $reason, $admin) {
            $locked = AffiliatePayout::query()->lockForUpdate()->findOrFail($payout->id);

            if (! in_array($locked->status, ['approved', 'processing'], true)) {
                throw new AffiliateException('Payout ini tidak sedang diproses.');
            }

            $this->wallet->adjust(
                $locked->affiliate,
                (float) $locked->amount,
                "Pengembalian payout #{$locked->id} yang gagal: {$reason}",
                $admin->id,
            );
            $oldStatus = $locked->status;
            $locked->update([
                'status' => 'failed',
                'failure_reason' => $reason,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);
            $this->audit->record('payout_failed', $locked->affiliate, $admin, ['status' => $oldStatus], ['status' => 'failed'], $reason);

            return $locked;
        });
    }

    public function reject(AffiliatePayout $payout, string $reason, Admin $admin): AffiliatePayout
    {
        return DB::transaction(function () use ($payout, $reason, $admin) {
            $locked = AffiliatePayout::query()->lockForUpdate()->findOrFail($payout->id);

            if ($locked->status !== 'pending') {
                throw new AffiliateException('Payout ini sudah diproses sebelumnya.');
            }

            $locked->update([
                'status' => 'rejected',
                'notes' => $reason,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);
            $this->audit->record('payout_rejected', $locked->affiliate, $admin, ['status' => 'pending'], ['status' => 'rejected'], $reason);

            return $locked;
        });
    }
}
