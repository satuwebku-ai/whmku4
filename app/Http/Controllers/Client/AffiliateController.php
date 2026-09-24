<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\Affiliate\AffiliateException;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateCampaign;
use App\Services\Affiliate\AffiliatePayoutService;
use App\Services\Affiliate\AffiliateReportService;
use App\Services\Affiliate\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    /**
     * Halaman utama area affiliate klien: kalau belum daftar, tampilkan
     * ajakan daftar; kalau sudah, tampilkan dashboard (dengan pesan
     * "menunggu approval" kalau statusnya masih pending).
     */
    public function index(AffiliateReportService $reports): View
    {
        $client = Auth::guard('client')->user();
        $affiliate = Affiliate::where('client_id', $client->id)->first();

        return view('client.affiliate.index', [
            'affiliate' => $affiliate,
            'summary' => $affiliate ? $reports->summaryFor($affiliate) : null,
            'campaigns' => $affiliate ? $affiliate->campaigns()->withCount('clicks')->latest()->get() : collect(),
            'referrals' => $affiliate ? $affiliate->referrals()->with(['client', 'conversion.invoice'])->latest()->limit(50)->get() : collect(),
            'commissions' => $affiliate ? $affiliate->commissions()->latest()->limit(20)->get() : collect(),
            'payouts' => $affiliate ? $affiliate->payouts()->latest()->limit(20)->get() : collect(),
            'rate' => $affiliate ? app(\App\Services\Affiliate\AffiliateCommissionService::class)->baseRateFor($affiliate) : null,
        ]);
    }

    public function register(AffiliateService $affiliates): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        $affiliates->register($client);

        return redirect()->route('client.affiliate.index')
            ->with('success', 'Pendaftaran affiliate berhasil dikirim. Menunggu persetujuan admin.');
    }

    public function updateBank(Request $request): RedirectResponse
    {
        $affiliate = $this->ownAffiliateOrFail();

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'bank_account_name' => ['required', 'string', 'max:150'],
        ]);

        $affiliate->update($data);

        return back()->with('success', 'Data rekening berhasil disimpan.');
    }

    public function requestPayout(Request $request, AffiliatePayoutService $payouts): RedirectResponse
    {
        $affiliate = $this->ownAffiliateOrFail();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            $payouts->request($affiliate, (float) $data['amount']);
        } catch (AffiliateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Permintaan payout berhasil dikirim, menunggu diproses admin.');
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $affiliate = $this->ownAffiliateOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'landing_url' => ['nullable', 'url', 'max:500'],
            'commission_type' => ['nullable', 'in:percentage,fixed'],
            'commission_value' => ['nullable', 'numeric', 'min:0'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
        ]);

        AffiliateCampaign::create([
            'affiliate_id' => $affiliate->id,
            'name' => $data['name'],
            'code' => strtoupper(Str::random(6)),
            'description' => $data['description'] ?? null,
            'landing_url' => $data['landing_url'] ?? null,
            'commission_type' => $data['commission_type'] ?? null,
            'commission_value' => $data['commission_value'] ?? null,
            'start_at' => $data['start_at'] ?? null,
            'end_at' => $data['end_at'] ?? null,
        ]);

        return back()->with('success', 'Link campaign baru berhasil dibuat.');
    }

    private function ownAffiliateOrFail(): Affiliate
    {
        $client = Auth::guard('client')->user();
        $affiliate = Affiliate::where('client_id', $client->id)->first();

        abort_if(! $affiliate, 404);

        return $affiliate;
    }
}
