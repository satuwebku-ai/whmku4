<?php

namespace App\Http\Controllers\Admin\Affiliate;

use App\Exceptions\Affiliate\AffiliateException;
use App\Http\Controllers\Controller;
use App\Models\AffiliatePayout;
use App\Services\Affiliate\AffiliatePayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayoutController extends Controller
{
    public function index(Request $request): View
    {
        $payouts = AffiliatePayout::with('affiliate.client')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.affiliate.payouts', ['payouts' => $payouts]);
    }

    public function approve(AffiliatePayout $payout, AffiliatePayoutService $payouts, Request $request): RedirectResponse
    {
        try {
            $payouts->approve($payout, $request->user('admin'));
        } catch (AffiliateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payout #{$payout->id} disetujui, saldo wallet affiliate sudah dipotong.");
    }

    public function markPaid(AffiliatePayout $payout, AffiliatePayoutService $payouts, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'payment_proof' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payouts->markPaid($payout, $request->user('admin'), $data['transaction_reference'] ?? null, $data['payment_proof'] ?? null);
        } catch (AffiliateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payout #{$payout->id} ditandai sudah dibayar.");
    }

    public function process(AffiliatePayout $payout, AffiliatePayoutService $payouts, Request $request): RedirectResponse
    {
        try {
            $payouts->startProcessing($payout, $request->user('admin'));
        } catch (AffiliateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payout #{$payout->id} masuk status processing.");
    }

    public function fail(AffiliatePayout $payout, AffiliatePayoutService $payouts, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $payouts->markFailed($payout, $data['reason'], $request->user('admin'));
        } catch (AffiliateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payout #{$payout->id} ditandai gagal dan saldo dikembalikan.");
    }

    public function reject(AffiliatePayout $payout, AffiliatePayoutService $payouts, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $payouts->reject($payout, $data['reason'], $request->user('admin'));
        } catch (AffiliateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payout #{$payout->id} ditolak.");
    }
}
