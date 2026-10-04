<?php

namespace App\Http\Controllers\Admin;

use App\Services\Billing\DeletionGuard;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{

    public function activeBootstrap(Request $request): View
    {
        return view('admin.clients.index', $this->clientListData($request, 'active'));
    }

    public function inactiveBootstrap(Request $request): View
    {
        return view('admin.clients.index', $this->clientListData($request, 'inactive'));
    }

    /**
     * Pratinjau versi Bootstrap -- data & filter SAMA PERSIS dengan
     * renderList(), cuma tampilannya beda. Halaman asli tidak tersentuh.
     */
    public function clientsBootstrap(Request $request): View
    {
        return view('admin.clients.index', $this->clientListData($request, null));
    }

    private function clientListData(Request $request, ?string $status): array
    {
        $clients = Client::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('company', 'like', "%{$request->search}%");
            }))
            ->withCount(['hostingAccounts', 'orders', 'invoices'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return ['clients' => $clients, 'activeStatus' => $status];
    }

    public function detailsBootstrap(Client $client): View
    {
        $client->loadCount(['hostingAccounts', 'orders', 'invoices']);
        $client->load([
            'orders' => fn ($q) => $q->latest()->limit(5),
            'invoices' => fn ($q) => $q->latest()->limit(5),
            'hostingAccounts' => fn ($q) => $q->latest()->limit(5),
            'balanceLogs' => fn ($q) => $q->latest()->limit(10),
        ]);

        return view('admin.clients.details', compact('client'));
    }

    /**
     * Admin menambah/mengurangi saldo klien manual — untuk refund,
     * kompensasi, atau koreksi. Selalu lewat adjustBalance() supaya
     * tercatat di buku besar, tidak pernah mengubah kolom balance
     * langsung.
     */
    public function adjustBalance(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'description' => ['required', 'string', 'min:5', 'max:255'],
            'token' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
        ], [
            'amount.required' => 'Isi nominal — boleh negatif untuk mengurangi saldo.',
            'description.min' => 'Tulis alasan penyesuaian dengan jelas (minimal 5 karakter).',
        ]);

        $amount = (float) $data['amount'];

        if (\App\Support\Money::cents($amount) === 0) {
            return back()->with('error', 'Nominal tidak boleh nol.');
        }

        $admin = auth('admin')->user();

        // Batas nominal sekali sesuaikan. Superadmin boleh sampai batas
        // keras; admin biasa dibatasi lebih rendah (atur di Setting
        // `balance_adjust_admin_limit`).
        $hardLimit = 50_000_000;
        $adminLimit = (float) \App\Models\Setting::get('balance_adjust_admin_limit', 1_000_000);
        $limit = $admin->role === 'superadmin' ? $hardLimit : min($adminLimit, $hardLimit);

        if (abs($amount) > $limit) {
            return back()->withInput()->with('error', 'Nominal melebihi batas penyesuaian Anda (' . \App\Support\Money::rupiah($limit) . ').'
                . ($admin->role === 'superadmin' ? '' : ' Minta superadmin untuk nominal lebih besar.'));
        }

        try {
            $credit = $client->adjustBalance(
                $amount,
                'admin_adjustment',
                "[Admin] {$data['description']}",
                null,
                $admin,
                // Token unik per tampilan form: dobel klik / kirim ulang
                // tidak menggandakan penyesuaian.
                "admin-adjust:{$client->id}:{$data['token']}",
            );
        } catch (\App\Exceptions\Billing\BillingException $e) {
            return back()->withInput()->with('error', 'Penyesuaian dibatalkan: hasil akhir saldo tidak boleh minus (saldo sekarang '
                . \App\Support\Money::rupiah($client->balance) . ').');
        }

        if (! $credit->wasRecentlyCreated) {
            return back()->with('success', 'Penyesuaian ini sudah diproses sebelumnya.');
        }

        \App\Models\ActivityLog::record(
            'payment',
            "Saldo {$client->name} disesuaikan admin",
            ($amount > 0 ? '+' : '-') . \App\Support\Money::rupiah(abs($amount)) . " — {$data['description']} (oleh {$admin->name})",
            route('admin.clients.details', $client),
            'warning',
            $client->id,
        );

        try {
            app(\App\Services\Notification\NotificationService::class)
                ->balanceAdjusted($client, $amount, $data['description'], (string) $admin->name);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Pemberitahuan penyesuaian saldo gagal: ' . $e->getMessage());
        }

        return back()->with('success', 'Saldo klien berhasil disesuaikan.');
    }

    public function createBootstrap(): View
    {
        return view('admin.clients.form', ['client' => new Client()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Client::create($data);

        return redirect()->route('admin.clients')->with('success', 'Klien baru berhasil ditambahkan.');
    }

    public function editBootstrap(Client $client): View
    {
        return view('admin.clients.form', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validated($request, $client->id);

        $client->update($data);

        return redirect()->route('admin.clients')->with('success', 'Data klien berhasil diperbarui.');
    }

    public function destroy(Client $client, DeletionGuard $guard): RedirectResponse
    {
        $name = $client->name;
        $files = \App\Models\TicketAttachment::query()
            ->whereHas('reply.ticket', fn ($q) => $q->where('client_id', $client->id))
            ->pluck('path')->filter()->all();

        $reason = $guard->deleteLocked($client, fn ($c) => $guard->forClient($c));

        if ($reason) {
            return back()->with('error', $reason);
        }

        // Tiket ikut terhapus lewat cascade; lampirannya di disk dibersihkan di sini.
        \Illuminate\Support\Facades\Storage::disk('local')->delete($files);

        $guard->audit('client', "Klien {$name} dihapus");

        return redirect()->route('admin.clients')->with('success', 'Klien berhasil dihapus.');
    }

    /**
     * Toggle status aktif/nonaktif klien.
     */
    public function status(Request $request): RedirectResponse
    {
        $client = Client::findOrFail($request->input('client_id'));
        $client->update(['status' => $client->status === 'active' ? 'inactive' : 'active']);

        return back()->with('success', "Status klien {$client->name} berhasil diubah.");
    }

    /**
     * Simpan catatan internal staf untuk klien ini.
     */
    public function notes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        $client = Client::findOrFail($data['client_id']);
        $client->update(['internal_notes' => $data['internal_notes']]);

        return back()->with('success', 'Catatan berhasil disimpan.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255', 'unique:clients,email' . ($ignoreId ? ",{$ignoreId}" : '')],
            'phone'   => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city'    => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'status'  => ['required', 'in:active,inactive'],
        ]);
    }
}
