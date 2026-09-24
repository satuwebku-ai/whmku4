<?php

namespace App\Http\Controllers\Admin\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\AffiliateFraudFlag;
use App\Services\Affiliate\AffiliateFraudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FraudReviewController extends Controller
{
    public function index(): View
    {
        return view('admin.affiliate.fraud', [
            'flags' => AffiliateFraudFlag::with(['affiliate.client', 'client', 'conversion.invoice'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function review(AffiliateFraudFlag $flag, AffiliateFraudService $fraud, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,blocked'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $fraud->review($flag, $data['status'], $request->user('admin')?->id, $data['notes'] ?? null);

        return back()->with('success', 'Hasil review fraud berhasil disimpan.');
    }
}