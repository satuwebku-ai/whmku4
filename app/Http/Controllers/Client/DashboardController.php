<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('client.dashboard.index', $this->dashboardData());
    }

    public function indexBootstrap(): View
    {
        return view('client.dashboard.index', $this->dashboardData());
    }

    private function dashboardData(): array
    {
        $client = Auth::guard('client')->user();

        $stats = [
            'services'       => $client->hostingAccounts()->where('status', 'active')->count(),
            'domains'        => $client->domains()->where('status', 'active')->count(),
            'unpaidInvoices' => $client->invoices()->whereIn('status', ['unpaid', 'overdue'])->count(),
            'openTickets'    => $client->tickets()->whereIn('status', ['open', 'answered', 'customer_reply'])->count(),
        ];

        $unpaidTotal = $client->invoices()->whereIn('status', ['unpaid', 'overdue'])->sum('total');

        $recentInvoices = $client->invoices()->latest()->take(5)->get();

        $expiringSoon = $client->domains()
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(60)])
            ->orderBy('expiry_date')
            ->get();

        $announcements = Announcement::live()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        return compact('client', 'stats', 'unpaidTotal', 'recentInvoices', 'expiringSoon', 'announcements');
    }
}
