<?php

namespace App\Http\Controllers\Admin\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommissionRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RuleController extends Controller
{
    public function index(): View
    {
        return view('admin.affiliate.rules', [
            'rules' => AffiliateCommissionRule::orderBy('priority')->orderBy('product_type')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'product_type' => ['nullable', 'in:domain,hosting,vps,reseller_hosting,reseller_domain,ssl,addon,mixed'],
            'event_type' => ['required', 'in:first_order,first_payment,every_payment,renewal,upgrade'],
            'commission_type' => ['required', 'in:percentage,fixed'],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'priority' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        if ($data['commission_type'] === 'percentage' && $data['commission_value'] > 100) {
            return back()->withErrors(['commission_value' => 'Persentase komisi tidak boleh lebih dari 100%.'])->withInput();
        }

        AffiliateCommissionRule::create($data + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', 'Aturan komisi berhasil dibuat.');
    }

    public function toggle(AffiliateCommissionRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('success', 'Status aturan komisi berhasil diubah.');
    }

    public function destroy(AffiliateCommissionRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', 'Aturan komisi berhasil dihapus.');
    }
}