<?php

namespace App\Http\Controllers\Admin\Affiliate;

use App\Exceptions\Affiliate\AffiliateException;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Services\Affiliate\AffiliateReportService;
use App\Services\Affiliate\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function index(AffiliateReportService $reports): View
    {
        return view('admin.affiliate.index', [
            'affiliates' => Affiliate::with('client')->latest()->paginate(20),
            'summary' => $reports->programSummary(),
        ]);
    }

    public function show(Affiliate $affiliate, AffiliateReportService $reports): View
    {
        return view('admin.affiliate.show', [
            'affiliate' => $affiliate->load('client', 'campaigns'),
            'summary' => $reports->summaryFor($affiliate),
            'conversions' => $affiliate->conversions()->with('invoice')->latest()->limit(20)->get(),
            'commissions' => $affiliate->commissions()->latest()->limit(20)->get(),
            'payouts' => $affiliate->payouts()->latest()->limit(20)->get(),
        ]);
    }

    public function approve(Affiliate $affiliate, AffiliateService $affiliates, Request $request): RedirectResponse
    {
        try {
            $affiliates->approve($affiliate, $request->user('admin'));
        } catch (AffiliateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Affiliate {$affiliate->code} disetujui.");
    }

    public function reject(Affiliate $affiliate, AffiliateService $affiliates, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $affiliates->reject($affiliate, $data['reason'], $request->user('admin'));

        return back()->with('success', "Affiliate {$affiliate->code} ditolak.");
    }

    public function suspend(Affiliate $affiliate, AffiliateService $affiliates, Request $request): RedirectResponse
    {
        $affiliates->suspend($affiliate, $request->user('admin'));

        return back()->with('success', "Affiliate {$affiliate->code} disuspend.");
    }
}
