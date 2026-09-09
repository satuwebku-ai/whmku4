<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\HostingAccount;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function orders(Request $request): View
    {
        return $this->renderList($request, null);
    }

    public function ordersBootstrap(Request $request): View
    {
        return view('admin.orders.index', $this->listData($request, null));
    }

    public function pending(Request $request): View
    {
        return $this->renderList($request, 'pending');
    }

    public function pendingBootstrap(Request $request): View
    {
        return view('admin.orders.index', $this->listData($request, 'pending'));
    }

    public function active(Request $request): View
    {
        return $this->renderList($request, 'active');
    }

    public function activeBootstrap(Request $request): View
    {
        return view('admin.orders.index', $this->listData($request, 'active'));
    }

    public function suspended(Request $request): View
    {
        return $this->renderList($request, 'suspended');
    }

    public function suspendedBootstrap(Request $request): View
    {
        return view('admin.orders.index', $this->listData($request, 'suspended'));
    }

    public function cancelled(Request $request): View
    {
        return $this->renderList($request, 'cancelled');
    }

    public function cancelledBootstrap(Request $request): View
    {
        return view('admin.orders.index', $this->listData($request, 'cancelled'));
    }

    private function renderList(Request $request, ?string $status): View
    {
        return view('admin.orders.index', $this->listData($request, $status));
    }

    private function listData(Request $request, ?string $status): array
    {
        $orders = Order::query()
            ->with('client')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->search, fn ($q) => $q->where('order_number', 'like', "%{$request->search}%")
                ->orWhere('product_name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return ['orders' => $orders, 'activeStatus' => $status];
    }

    public function details(Order $order): View
    {
        $order->load(['client', 'hostingAccount', 'domain', 'invoice', 'invoiceItem.invoice']);

        return view('admin.orders.details', compact('order'));
    }

    public function detailsBootstrap(Order $order): View
    {
        $order->load(['client', 'hostingAccount', 'domain', 'invoice', 'invoiceItem.invoice']);

        return view('admin.orders.details', compact('order'));
    }

    public function create(): View
    {
        $clients = Client::orderBy('name')->get();
        $hostingAccounts = HostingAccount::orderBy('domain')->get();

        return view('admin.orders.form', ['order' => new Order(), 'clients' => $clients, 'hostingAccounts' => $hostingAccounts]);
    }

    public function createBootstrap(): View
    {
        $clients = Client::orderBy('name')->get();
        $hostingAccounts = HostingAccount::orderBy('domain')->get();

        return view('admin.orders.form', ['order' => new Order(), 'clients' => $clients, 'hostingAccounts' => $hostingAccounts]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Order::create($data);

        return redirect()->route('admin.orders')->with('success', 'Order berhasil dibuat.');
    }

    public function edit(Order $order): View
    {
        $clients = Client::orderBy('name')->get();
        $hostingAccounts = HostingAccount::orderBy('domain')->get();

        return view('admin.orders.form', ['order' => $order, 'clients' => $clients, 'hostingAccounts' => $hostingAccounts]);
    }

    public function editBootstrap(Order $order): View
    {
        $clients = Client::orderBy('name')->get();
        $hostingAccounts = HostingAccount::orderBy('domain')->get();

        return view('admin.orders.form', ['order' => $order, 'clients' => $clients, 'hostingAccounts' => $hostingAccounts]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $data = $this->validated($request);

        $order->update($data);

        return redirect()->route('admin.orders')->with('success', 'Order berhasil diperbarui.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $order->delete();

        return redirect()->route('admin.orders')->with('success', 'Order berhasil dihapus.');
    }

    /**
     * Terima order — set status jadi aktif.
     */
    public function accept(Request $request): RedirectResponse
    {
        $order = Order::findOrFail($request->input('order_id'));
        $order->update(['status' => 'active']);

        return back()->with('success', "Order #{$order->order_number} diterima & diaktifkan.");
    }

    /**
     * Batalkan order.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $order = Order::findOrFail($request->input('order_id'));
        $order->update(['status' => 'cancelled']);

        return back()->with('success', "Order #{$order->order_number} dibatalkan.");
    }

    /**
     * Kembalikan order ke status pending.
     */
    public function markPending(Request $request): RedirectResponse
    {
        $order = Order::findOrFail($request->input('order_id'));
        $order->update(['status' => 'pending']);

        return back()->with('success', "Order #{$order->order_number} dikembalikan ke pending.");
    }

    /**
     * Simpan catatan internal staf untuk order ini.
     */
    public function orderNotes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        $order = Order::findOrFail($data['order_id']);
        $order->update(['internal_notes' => $data['internal_notes']]);

        return back()->with('success', 'Catatan order berhasil disimpan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id'           => ['required', 'exists:clients,id'],
            'hosting_account_id'  => ['nullable', 'exists:hosting_accounts,id'],
            'product_name'        => ['required', 'string', 'max:255'],
            'order_type'          => ['required', 'in:hosting,domain,vps,other'],
            'amount'              => ['required', 'numeric', 'min:0'],
            'status'              => ['required', 'in:pending,active,suspended,cancelled'],
        ]);
    }
}
