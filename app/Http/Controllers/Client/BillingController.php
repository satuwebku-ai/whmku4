<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(): View
    {
        $client = Auth::guard('client')->user();

        $unpaidInvoices = $client->invoices()
            ->whereIn('status', ['unpaid', 'overdue'])
            ->orderBy('due_date')
            ->get();

        $recentInvoices = $client->invoices()
            ->latest('issue_date')
            ->latest('id')
            ->take(6)
            ->get();

        $recentPayments = $client->payments()
            ->with(['invoice', 'gateway'])
            ->latest()
            ->take(8)
            ->get();

        $summary = [
            'unpaid_count' => $unpaidInvoices->count(),
            'unpaid_total' => (float) $unpaidInvoices->sum('total'),
            'paid_total' => (float) $client->invoices()->where('status', 'paid')->sum('total'),
            'balance' => (float) $client->balance,
        ];

        return view('client.billing.index', compact(
            'client',
            'summary',
            'unpaidInvoices',
            'recentInvoices',
            'recentPayments',
        ));
    }
}
