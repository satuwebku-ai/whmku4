<?php

namespace App\Http\Controllers\Admin\Affiliate;

use App\Exceptions\Affiliate\AffiliateException;
use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Services\Affiliate\AffiliateCommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(Request $request): View
    {
        $commissions = AffiliateCommission::with('affiliate.client', 'conversion.invoice')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.affiliate.commissions', ['commissions' => $commissions]);
    }

    public function approve(AffiliateCommission $commission, AffiliateCommissionService $commissions, Request $request): RedirectResponse
    {
        try {
            $commissions->approve($commission, $request->user('admin'));
        } catch (AffiliateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Komisi #{$commission->id} disetujui dan sudah masuk wallet affiliate.");
    }

    public function cancel(AffiliateCommission $commission, AffiliateCommissionService $commissions, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $commissions->cancel($commission, $data['reason'], $request->user('admin'));
        } catch (AffiliateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Komisi #{$commission->id} dibatalkan.");
    }

    public function reverse(AffiliateCommission $commission, AffiliateCommissionService $commissions, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $commissions->reverse($commission, $data['reason'], $request->user('admin'));
        } catch (AffiliateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Komisi #{$commission->id} berhasil direversal dan saldo wallet dikoreksi.");
    }
}
