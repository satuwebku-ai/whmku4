<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BillingDashboardController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->period($request);
        $paidStatuses = ['paid'];

        $revenue = Invoice::whereIn('status', $paidStatuses)
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('total');

        $outstanding = Invoice::whereIn('status', ['unpaid', 'overdue'])->sum('total');
        $overdue = Invoice::where('status', 'overdue')->sum('total');
        $paidCount = Invoice::where('status', 'paid')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->count();
        $paymentsToday = Payment::where('status', 'paid')
            ->whereDate('paid_at', now()->toDateString())
            ->sum('total');
        $topupRevenue = Invoice::where('status', 'paid')
            ->where('is_topup', true)
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('total');

        $dailyRevenue = collect(range(0, $from->diffInDays($to)))
            ->map(function (int $offset) use ($from) {
                $date = $from->copy()->addDays($offset);
                return [
                    'date' => $date->toDateString(),
                    'label' => $date->format('d M'),
                    'value' => (float) Invoice::where('status', 'paid')
                        ->whereDate('paid_at', $date)
                        ->sum('total'),
                ];
            });

        $recentPayments = Payment::with(['invoice', 'client'])
            ->where('status', 'paid')
            ->latest('paid_at')
            ->take(8)
            ->get();

        $overdueInvoices = Invoice::with('client')
            ->where('status', 'overdue')
            ->orderBy('due_date')
            ->take(8)
            ->get();

        $reconciliationIssues = [
            'paid_invoice_without_charge' => Invoice::where('status', 'paid')
                ->whereDoesntHave('transactions', fn ($q) => $q->where('type', 'charge'))
                ->count(),
            'paid_payment_without_invoice' => Payment::where('status', 'paid')
                ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'paid'))
                ->count(),
        ];

        return view('admin.billing.dashboard', compact(
            'from', 'to', 'revenue', 'outstanding', 'overdue', 'paidCount',
            'paymentsToday', 'topupRevenue', 'dailyRevenue', 'recentPayments',
            'overdueInvoices', 'reconciliationIssues'
        ));
    }

    private function period(Request $request): array
    {
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfDay();
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : $to->copy()->startOfMonth();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
