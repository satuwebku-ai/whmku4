<?php

namespace App\Services\Affiliate;

use App\Exceptions\Affiliate\AffiliateException;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateCommissionReversal;
use App\Models\AffiliatePayout;
use App\Models\AffiliateWallet;
use App\Models\AffiliateWalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat saldo affiliate_wallets boleh diubah. Pola & alasan
 * persis meniru App\Services\Billing\CreditService / Client::adjustBalance():
 * saldo tidak pernah di-update langsung, selalu lewat baris ledger di
 * affiliate_wallet_transactions supaya ada jejak audit lengkap.
 */
class AffiliateWalletService
{
    public function walletFor(Affiliate $affiliate): AffiliateWallet
    {
        return AffiliateWallet::firstOrCreate(['affiliate_id' => $affiliate->id], ['balance' => 0]);
    }

    public function balance(Affiliate $affiliate): float
    {
        return (float) $this->walletFor($affiliate)->balance;
    }

    /**
     * Kredit wallet karena komisi disetujui.
     */
    public function creditFromCommission(AffiliateCommission $commission): AffiliateWalletTransaction
    {
        return $this->mutate(
            $commission->affiliate,
            (float) ($commission->net_amount ?? $commission->amount),
            'commission',
            "Komisi konversi #{$commission->affiliate_conversion_id}",
            commissionId: $commission->id,
        );
    }

    /**
     * Debit wallet karena payout disetujui/dicairkan. Melempar exception
     * kalau saldo tidak cukup -- dicek SETELAH wallet dikunci (lihat
     * mutate(): parameter requireSufficientFunds), bukan sebelumnya.
     *
     * Riwayat: sebelumnya dicek di sini lewat balance() SEBELUM mutate()
     * mengunci baris wallet -- kalau dua payout affiliate yang sama
     * disetujui bersamaan (atau tombol "Setujui" diklik dobel), keduanya
     * bisa lolos cek dengan saldo lama yang sama, lalu sama-sama memotong
     * wallet sampai minus. Sudah dipindah supaya SATU-SATUNYA cek
     * kecukupan saldo terjadi memakai baris yang sudah terkunci.
     */
    public function debitFromPayout(AffiliatePayout $payout): AffiliateWalletTransaction
    {
        return $this->mutate(
            $payout->affiliate,
            -1 * (float) $payout->amount,
            'payout',
            "Payout #{$payout->id}",
            payoutId: $payout->id,
            requireSufficientFunds: true,
        );
    }

    /**
     * Penyesuaian manual oleh admin (mis. komisi yang dibatalkan setelah
     * terlanjur cair, dikoreksi manual).
     */
    public function adjust(Affiliate $affiliate, float $amount, string $description, ?int $adminId = null): AffiliateWalletTransaction
    {
        return $this->mutate($affiliate, $amount, 'adjustment', $description, adminId: $adminId);
    }

    public function reverseCommission(
        AffiliateCommissionReversal $reversal,
        ?int $adminId = null,
    ): AffiliateWalletTransaction {
        return $this->mutate(
            $reversal->affiliate,
            -1 * (float) $reversal->amount,
            'adjustment',
            "Reversal komisi #{$reversal->affiliate_commission_id}: {$reversal->reason}",
            adminId: $adminId,
            reversalId: $reversal->id,
        );
    }

    private function mutate(
        Affiliate $affiliate,
        float $amount,
        string $type,
        string $description,
        ?int $commissionId = null,
        ?int $payoutId = null,
        ?int $adminId = null,
        ?int $reversalId = null,
        bool $requireSufficientFunds = false,
    ): AffiliateWalletTransaction {
        return DB::transaction(function () use ($affiliate, $amount, $type, $description, $commissionId, $payoutId, $adminId, $reversalId, $requireSufficientFunds) {
            // firstOrCreate dulu di luar lock (supaya row pasti ada),
            // baru dikunci sungguhan di sini -- mencegah race condition
            // dua kredit/debit wallet yang sama diproses bersamaan.
            $this->walletFor($affiliate);
            $wallet = AffiliateWallet::where('affiliate_id', $affiliate->id)->lockForUpdate()->firstOrFail();

            // Dicek DI SINI, pakai baris yang SUDAH terkunci -- bukan
            // sebelum lock diambil -- supaya dua debit bersamaan untuk
            // affiliate yang sama tidak bisa sama-sama lolos dari saldo
            // yang sama sebelum salah satunya benar-benar tercatat.
            if ($requireSufficientFunds && ((float) $wallet->balance + $amount) < 0) {
                throw new AffiliateException('Saldo wallet affiliate tidak cukup untuk payout ini.');
            }

            $wallet->increment('balance', $amount);

            return AffiliateWalletTransaction::create([
                'affiliate_wallet_id' => $wallet->id,
                'affiliate_id' => $affiliate->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $wallet->fresh()->balance,
                'description' => $description,
                'affiliate_commission_id' => $commissionId,
                'affiliate_payout_id' => $payoutId,
                'affiliate_commission_reversal_id' => $reversalId,
                'admin_id' => $adminId,
            ]);
        });
    }
}
