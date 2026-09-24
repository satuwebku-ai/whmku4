<?php

namespace Tests\Feature;

use App\Exceptions\Affiliate\AffiliateException;
use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\AffiliateCommission;
use App\Models\Client;
use App\Services\Affiliate\AffiliateCommissionService;
use App\Services\Affiliate\AffiliatePayoutService;
use App\Services\Affiliate\AffiliateWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wallet affiliate tidak boleh bisa minus meski dua payout untuk affiliate
 * yang sama diproses hampir bersamaan (dua klik "Setujui", atau dua tab
 * admin). Sebelum diperbaiki, AffiliateWalletService::debitFromPayout()
 * mengecek kecukupan saldo SEBELUM baris wallet dikunci, dan
 * AffiliatePayoutService::approve() tidak mengunci baris payout-nya sendiri
 * -- kombinasi keduanya bisa membuat satu payout terpotong dua kali dari
 * wallet yang sama.
 */
class AffiliatePayoutAndCommissionRaceTest extends TestCase
{
    use RefreshDatabase;

    private function affiliateWithBalance(float $balance): Affiliate
    {
        $client = Client::create([
            'name' => 'Klien Afiliasi', 'email' => 'afiliasi' . uniqid() . '@test.local',
            'password' => 'x', 'status' => 'active', 'balance' => 0,
        ]);

        $affiliate = Affiliate::create([
            'client_id' => $client->id, 'code' => Affiliate::generateUniqueCode(), 'status' => 'approved',
            'bank_name' => 'BCA', 'bank_account_number' => '12345', 'bank_account_name' => 'Klien Afiliasi',
        ]);

        app(AffiliateWalletService::class)->adjust($affiliate, $balance, 'Saldo awal pengujian');

        return $affiliate;
    }

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Admin', 'username' => 'admin' . uniqid(), 'email' => 'admin' . uniqid() . '@test.local', 'password' => 'x', 'role' => 'superadmin', 'is_active' => true]);
    }

    public function test_payout_kedua_ditolak_kalau_saldo_sudah_terpakai_payout_pertama(): void
    {
        // Simulasi hasil dari race: dua payout untuk affiliate yang sama
        // sudah lolos dibuat (masing-masing valid sendiri-sendiri), lalu
        // admin menyetujui keduanya. Yang kedua HARUS gagal, bukan ikut
        // memotong wallet sampai minus.
        $affiliate = $this->affiliateWithBalance(100000);
        $admin = $this->admin();
        $service = app(AffiliatePayoutService::class);

        $payoutA = AffiliatePayout::create(['affiliate_id' => $affiliate->id, 'amount' => 80000, 'status' => 'pending']);
        $payoutB = AffiliatePayout::create(['affiliate_id' => $affiliate->id, 'amount' => 80000, 'status' => 'pending']);

        $service->approve($payoutA, $admin);
        $this->assertSame(20000.0, app(AffiliateWalletService::class)->balance($affiliate->fresh()));

        $this->expectException(AffiliateException::class);
        try {
            $service->approve($payoutB, $admin);
        } finally {
            // Yang gagal HARUS tetap 'pending', bukan berubah jadi 'approved'
            // dengan wallet yang sudah kadung minus.
            $this->assertSame('pending', $payoutB->fresh()->status);
            $this->assertSame(20000.0, app(AffiliateWalletService::class)->balance($affiliate->fresh()));
        }
    }

    public function test_approve_komisi_dua_kali_pada_baris_yang_sama_hanya_mengkredit_sekali(): void
    {
        // Simulasi hasil "approve() dipanggil dua kali pada baris yang
        // sama" (dua klik / dua request nyaris bersamaan yang keduanya
        // sempat lolos baca status lama sebelum diperbaiki). Setelah
        // diperbaiki, panggilan kedua HARUS gagal dan wallet HANYA
        // ke-kredit satu kali.
        $affiliate = $this->affiliateWithBalance(0);
        $admin = $this->admin();
        $service = app(AffiliateCommissionService::class);

        $commission = AffiliateCommission::create([
            'affiliate_id' => $affiliate->id, 'amount' => 25000, 'status' => 'pending',
        ]);

        $service->approve($commission, $admin);
        $this->assertSame(25000.0, app(\App\Services\Affiliate\AffiliateWalletService::class)->balance($affiliate->fresh()));

        $this->expectException(AffiliateException::class);
        try {
            $service->approve($commission->fresh(), $admin);
        } finally {
            $this->assertSame(25000.0, app(\App\Services\Affiliate\AffiliateWalletService::class)->balance($affiliate->fresh()));
        }
    }

    public function test_approve_payout_yang_sudah_diproses_ditolak(): void
    {
        $affiliate = $this->affiliateWithBalance(100000);
        $admin = $this->admin();
        $service = app(AffiliatePayoutService::class);

        $payout = AffiliatePayout::create(['affiliate_id' => $affiliate->id, 'amount' => 50000, 'status' => 'pending']);
        $service->approve($payout, $admin);

        $this->expectException(AffiliateException::class);
        $service->approve($payout, $admin);
    }
}
