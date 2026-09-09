<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard utama admin — sudah pakai data asli dari database (Fase 2).
     */
    public function index(): View
    {
        return view('admin.dashboard.index', $this->dashboardData());
    }

    /**
     * Pratinjau dashboard versi Bootstrap -- data SAMA PERSIS dengan
     * index(), cuma tampilannya beda. Dipisah supaya dashboard asli
     * tidak tersentuh sampai versi baru ini benar-benar siap gantikan.
     */
    public function indexBootstrap(): View
    {
        return view('admin.dashboard.index', $this->dashboardData());
    }

    private function dashboardData(): array
    {
        $totalClients = Client::count();
        $clientsLastMonth = Client::where('created_at', '<', now()->subMonth())->count();
        $clientDelta = $this->percentDelta($clientsLastMonth, $totalClients);

        $activeServices = HostingAccount::where('status', 'active')->count();
        $activeServicesLastMonth = HostingAccount::where('status', 'active')
            ->where('created_at', '<', now()->subMonth())->count();
        $serviceDelta = $this->percentDelta($activeServicesLastMonth, $activeServices);

        $pendingOrders = Order::where('status', 'pending')->count();
        $pendingOrdersLastWeek = Order::where('status', 'pending')
            ->where('created_at', '<', now()->subWeek())->count();

        $revenueThisMonth = Invoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('total');
        $revenueLastMonth = Invoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->whereYear('paid_at', now()->subMonth()->year)
            ->sum('total');
        $revenueDelta = $this->percentDelta($revenueLastMonth, $revenueThisMonth);

        $stats = [
            [
                'label' => 'Total Klien',
                'value' => number_format($totalClients, 0, ',', '.'),
                'delta' => $clientDelta,
                'trend' => str_starts_with($clientDelta, '-') ? 'down' : 'up',
                'icon'  => 'users',
            ],
            [
                'label' => 'Layanan Aktif',
                'value' => number_format($activeServices, 0, ',', '.'),
                'delta' => $serviceDelta,
                'trend' => str_starts_with($serviceDelta, '-') ? 'down' : 'up',
                'icon'  => 'server',
            ],
            [
                'label' => 'Order Pending',
                'value' => number_format($pendingOrders, 0, ',', '.'),
                'delta' => ($pendingOrders - $pendingOrdersLastWeek >= 0 ? '+' : '') . ($pendingOrders - $pendingOrdersLastWeek),
                'trend' => ($pendingOrders - $pendingOrdersLastWeek) < 0 ? 'down' : 'up',
                'icon'  => 'clipboard',
            ],
            [
                'label' => 'Pendapatan Bulan Ini',
                'value' => 'Rp ' . number_format((float) $revenueThisMonth, 0, ',', '.'),
                'delta' => $revenueDelta,
                'trend' => str_starts_with($revenueDelta, '-') ? 'down' : 'up',
                'icon'  => 'wallet',
            ],
        ];

        $recentOrders = Order::with('client')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Order $order) => [
                'id'      => '#' . $order->order_number,
                'client'  => $order->client->name ?? '—',
                'product' => $order->product_name,
                'status'  => $order->status,
                'total'   => 'Rp ' . number_format((float) $order->amount, 0, ',', '.'),
            ]);

        $openTickets = Ticket::whereIn('status', ['open', 'customer_reply'])
            ->with('client')
            ->orderByDesc('last_reply_at')
            ->take(5)
            ->get();

        return compact('stats', 'recentOrders', 'openTickets');
    }

    private function percentDelta(int|float $previous, int|float $current): string
    {
        if ($previous <= 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $percent = (($current - $previous) / $previous) * 100;

        return ($percent >= 0 ? '+' : '') . number_format($percent, 1) . '%';
    }
}
