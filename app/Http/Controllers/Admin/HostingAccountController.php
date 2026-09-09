<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\HostingAccount;
use App\Models\Server;
use App\Services\Hosting\HostingPanelFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HostingAccountController extends Controller
{
    public function hostingAccounts(Request $request): View
    {
        return $this->renderList($request, null);
    }

    public function hostingAccountsBootstrap(Request $request): View
    {
        return view('admin.hosting-accounts.index', $this->listData($request, null));
    }

    public function pending(Request $request): View
    {
        return $this->renderList($request, 'pending');
    }

    public function pendingBootstrap(Request $request): View
    {
        return view('admin.hosting-accounts.index', $this->listData($request, 'pending'));
    }

    public function active(Request $request): View
    {
        return $this->renderList($request, 'active');
    }

    public function activeBootstrap(Request $request): View
    {
        return view('admin.hosting-accounts.index', $this->listData($request, 'active'));
    }

    public function suspended(Request $request): View
    {
        return $this->renderList($request, 'suspended');
    }

    public function suspendedBootstrap(Request $request): View
    {
        return view('admin.hosting-accounts.index', $this->listData($request, 'suspended'));
    }

    public function terminated(Request $request): View
    {
        return $this->renderList($request, 'terminated');
    }

    public function terminatedBootstrap(Request $request): View
    {
        return view('admin.hosting-accounts.index', $this->listData($request, 'terminated'));
    }

    /**
     * Layanan aktif yang belum tertaut ke produk manapun — klien tidak
     * bisa mengajukan upgrade mandiri sampai ini diisi admin. Terpisah
     * dari renderList() karena filternya bukan status, tapi product_id.
     */
    public function unlinked(Request $request): View
    {
        return view('admin.hosting-accounts.index', $this->unlinkedData($request));
    }

    public function unlinkedBootstrap(Request $request): View
    {
        return view('admin.hosting-accounts.index', $this->unlinkedData($request));
    }

    private function unlinkedData(Request $request): array
    {
        $accounts = HostingAccount::query()
            ->with(['client', 'serverModel'])
            ->where('status', 'active')
            ->whereNull('product_id')
            ->when($request->search, fn ($q) => $q->where('domain', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return ['accounts' => $accounts, 'activeStatus' => 'unlinked'];
    }

    private function renderList(Request $request, ?string $status): View
    {
        return view('admin.hosting-accounts.index', $this->listData($request, $status));
    }

    private function listData(Request $request, ?string $status): array
    {
        // VPS/cloud dikelola lewat menu Layanan VPS tersendiri -- form
        // hosting di sini penuh istilah cPanel (nama plan WHM, username
        // panel, SSO) yang tidak berlaku untuk mesin virtual, jadi
        // dikecualikan supaya tidak salah diedit dari sini.
        $cloudServerIds = \App\Models\Server::whereIn('panel', ['idcloudhost'])->pluck('id');

        $accounts = HostingAccount::query()
            ->with(['client', 'serverModel'])
            ->where(fn ($q) => $q->whereNotIn('server_id', $cloudServerIds)->orWhereNull('server_id'))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->search, fn ($q) => $q->where('domain', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return ['accounts' => $accounts, 'activeStatus' => $status];
    }

    public function details(HostingAccount $hostingAccount): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfVps($hostingAccount)) {
            return $redirect;
        }

        return view('admin.hosting-accounts.details', $this->detailsData($hostingAccount));
    }

    public function detailsBootstrap(HostingAccount $hostingAccount): View|RedirectResponse
    {
        return $this->details($hostingAccount);
    }

    /**
     * VPS punya halaman sendiri (Layanan VPS) dengan kontrol yang sesuai
     * -- form hosting di sini penuh istilah cPanel yang tidak berlaku
     * untuk mesin virtual, dan mengeditnya dari sini bisa merusak data
     * spesifikasi VM yang tersimpan sebagai JSON di kolom package.
     */
    private function redirectIfVps(HostingAccount $account): ?RedirectResponse
    {
        $isVps = $account->serverModel && $account->serverModel->panel === 'idcloudhost';

        return $isVps
            ? redirect()->route('admin.vps')->with('error', "\"{$account->domain}\" adalah VPS — kelola lewat menu Layanan VPS, bukan Hosting Account.")
            : null;
    }

    private function detailsData(HostingAccount $hostingAccount): array
    {
        $hostingAccount->load(['client', 'serverModel', 'orders', 'activeAddons', 'options']);

        // Cuma dicoba untuk akun otomatis (terhubung server) — akun
        // manual tidak punya cara diperiksa lewat API sama sekali.
        // Dibungkus try-catch supaya server yang lambat/tidak
        // merespons tidak sampai membuat SELURUH halaman detail gagal
        // dimuat, cuma bagian SSL-nya saja yang kosong.
        $sslStatus = null;

        if ($hostingAccount->serverModel && $hostingAccount->domain) {
            try {
                $service = HostingPanelFactory::make($hostingAccount->serverModel);

                if (method_exists($service, 'getSslStatus')) {
                    $result = $service->getSslStatus($hostingAccount->domain);
                    $sslStatus = $result['success'] ? $result : null;
                }
            } catch (\Throwable $e) {
                $sslStatus = null;
            }
        }

        return ['account' => $hostingAccount, 'sslStatus' => $sslStatus];
    }

    /**
     * Bukan fitur untuk pengguna akhir — alat bantu sementara untuk
     * melihat persis respons mentah WHM soal SSL, kalau status yang
     * tampil di halaman detail ternyata tidak sesuai kondisi sungguhan.
     */
    public function debugSsl(HostingAccount $hostingAccount)
    {
        if (! $hostingAccount->serverModel) {
            return response()->json(['error' => 'Layanan ini tidak terhubung server.']);
        }

        $service = HostingPanelFactory::make($hostingAccount->serverModel);

        if (! method_exists($service, 'debugSslStatus')) {
            return response()->json(['error' => 'Panel server ini tidak mendukung pengecekan SSL.']);
        }

        return response()->json($service->debugSslStatus(), 200, [], JSON_PRETTY_PRINT);
    }

    public function create(): View
    {
        $clients = Client::orderBy('name')->get();
        $servers = Server::where('is_active', true)->orderBy('name')->get();
        $products = \App\Models\Product::with('category')->where('is_active', true)->orderBy('name')->get();

        return view('admin.hosting-accounts.form', [
            'account' => new HostingAccount(),
            'clients' => $clients,
            'servers' => $servers,
            'products' => $products,
        ]);
    }

    public function createBootstrap(): View
    {
        $clients = Client::orderBy('name')->get();
        $servers = Server::where('is_active', true)->orderBy('name')->get();
        $products = \App\Models\Product::with('category')->where('is_active', true)->orderBy('name')->get();

        return view('admin.hosting-accounts.form', [
            'account' => new HostingAccount(),
            'clients' => $clients,
            'servers' => $servers,
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $provision = $request->boolean('provision_now');
        $password = $request->input('provision_password');

        $data['provision_status'] = 'manual';
        $data['provision_message'] = null;

        if ($provision) {
            if (! $data['server_id'] || ! $request->filled('username') || ! $password) {
                return back()->withInput()->with('error', 'Untuk auto-provisioning, pilih server, isi username panel, dan password.');
            }

            $server = Server::findOrFail($data['server_id']);

            $result = HostingPanelFactory::make($server)->createAccount([
                'domain'   => $data['domain'],
                'username' => $data['username'],
                'password' => $password,
                'package'  => $data['package'],
                'email'    => Client::find($data['client_id'])?->email ?? '',
            ]);

            $data['provision_status'] = $result['success'] ? 'provisioned' : 'failed';
            $data['provision_message'] = $result['message'];

            if ($result['success']) {
                $data['status'] = 'active';

                // Provider VM (IDCloudHost) mengembalikan UUID VM sebagai
                // 'username' -- wajib disimpan apa adanya, karena itu
                // pengenal untuk semua operasi VM berikutnya. Panel cPanel
                // dkk tidak mengembalikan ini, jadi username tetap dipakai.
                if (! empty($result['username'])) {
                    $data['username'] = $result['username'];
                }

                if (! empty($result['ip'])) {
                    $data['client_details'] = trim(
                        (string) ($data['client_details'] ?? '') . "\n"
                        . "IP Server: {$result['ip']}\n"
                        . "Username: {$request->input('username')}\n"
                        . "Password: {$password}"
                    );
                }
            }

            $account = HostingAccount::create($data);

            return $result['success']
                ? redirect()->route('admin.hosting-accounts.details', $account)->with('success', 'Hosting account berhasil dibuat & di-provision otomatis di server.')
                : redirect()->route('admin.hosting-account.edit.page', $account)->with('error', 'Data tersimpan, tapi provisioning otomatis GAGAL: ' . $result['message']);
        }

        HostingAccount::create($data);

        return redirect()->route('admin.hosting-accounts')->with('success', 'Hosting account berhasil dibuat (manual, tanpa provisioning otomatis).');
    }

    public function edit(HostingAccount $hostingAccount): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfVps($hostingAccount)) {
            return $redirect;
        }

        $clients = Client::orderBy('name')->get();
        $servers = Server::where('is_active', true)->orderBy('name')->get();
        $products = \App\Models\Product::with('category')->where('is_active', true)->orderBy('name')->get();

        return view('admin.hosting-accounts.form', [
            'account' => $hostingAccount,
            'clients' => $clients,
            'servers' => $servers,
            'products' => $products,
        ]);
    }

    public function editBootstrap(HostingAccount $hostingAccount): View
    {
        $clients = Client::orderBy('name')->get();
        $servers = Server::where('is_active', true)->orderBy('name')->get();
        $products = \App\Models\Product::with('category')->where('is_active', true)->orderBy('name')->get();

        return view('admin.hosting-accounts.form', [
            'account' => $hostingAccount,
            'clients' => $clients,
            'servers' => $servers,
            'products' => $products,
        ]);
    }

    public function update(Request $request, HostingAccount $hostingAccount): RedirectResponse
    {
        $data = $this->validated($request);

        $hostingAccount->update($data);

        return redirect()->route('admin.hosting-accounts')->with('success', 'Hosting account berhasil diperbarui.');
    }

    public function destroy(HostingAccount $hostingAccount): RedirectResponse
    {
        $hostingAccount->delete();

        return redirect()->route('admin.hosting-accounts')->with('success', 'Hosting account berhasil dihapus (catatan: akun di server panel TIDAK ikut terhapus).');
    }

    public function suspend(HostingAccount $hostingAccount): RedirectResponse
    {
        return $this->panelAction($hostingAccount, 'suspendAccount', 'suspended', 'Hosting account berhasil disuspend.');
    }

    public function unsuspend(HostingAccount $hostingAccount): RedirectResponse
    {
        return $this->panelAction($hostingAccount, 'unsuspendAccount', 'active', 'Hosting account berhasil diaktifkan kembali.');
    }

    public function terminate(HostingAccount $hostingAccount): RedirectResponse
    {
        return $this->panelAction($hostingAccount, 'terminateAccount', 'terminated', 'Hosting account berhasil di-terminate dari server.');
    }

    /**
     * Setujui pengajuan pembatalan dari klien — ini yang benar-benar
     * men-terminate layanan (lewat jalur panelAction yang sama dengan
     * tombol Terminate manual, supaya API panel tetap dipanggil).
     */
    public function approveCancellation(Request $request, HostingAccount $hostingAccount): RedirectResponse
    {
        if (! $hostingAccount->hasPendingCancellation()) {
            return back()->with('error', 'Tidak ada pengajuan pembatalan yang menunggu untuk layanan ini.');
        }

        $hostingAccount->update([
            'cancellation_status' => 'approved',
            'cancellation_admin_note' => $request->input('admin_note'),
        ]);

        // Akun manual (tidak terhubung server/panel) tidak bisa dihentikan
        // lewat API — statusnya diubah langsung di sini. Akun yang
        // terhubung server tetap lewat panelAction supaya API panel
        // benar-benar dipanggil untuk mematikan akunnya.
        if (! $hostingAccount->serverModel || ! $hostingAccount->username) {
            $hostingAccount->update(['status' => 'terminated']);

            return back()->with('success', 'Pembatalan disetujui. Karena akun ini manual, hentikan aksesnya secara manual juga di server bila perlu.');
        }

        return $this->panelAction(
            $hostingAccount,
            'terminateAccount',
            'terminated',
            'Pembatalan disetujui dan layanan berhasil dihentikan.'
        );
    }

    /**
     * Tolak pengajuan pembatalan — layanan tetap berjalan seperti biasa.
     */
    public function declineCancellation(Request $request, HostingAccount $hostingAccount): RedirectResponse
    {
        if (! $hostingAccount->hasPendingCancellation()) {
            return back()->with('error', 'Tidak ada pengajuan pembatalan yang menunggu untuk layanan ini.');
        }

        $hostingAccount->update([
            'cancellation_status' => 'declined',
            'cancellation_admin_note' => $request->input('admin_note'),
        ]);

        return back()->with('success', 'Pengajuan pembatalan ditolak. Layanan tetap aktif.');
    }

    /**
     * Simpan catatan internal staf untuk hosting account ini.
     */
    public function notes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hosting_account_id' => ['required', 'exists:hosting_accounts,id'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        $account = HostingAccount::findOrFail($data['hosting_account_id']);
        $account->update(['internal_notes' => $data['internal_notes']]);

        return back()->with('success', 'Catatan berhasil disimpan.');
    }

    public function changePassword(Request $request, HostingAccount $hostingAccount): RedirectResponse
    {
        $data = $request->validate([
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        if (! $hostingAccount->serverModel || ! $hostingAccount->username) {
            return back()->with('error', 'Akun ini tidak terhubung ke server panel (dibuat manual), jadi tidak bisa diubah dari sini.');
        }

        $result = HostingPanelFactory::make($hostingAccount->serverModel)->changePassword($hostingAccount->username, $data['new_password']);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Password cPanel berhasil diubah.' : 'Gagal mengubah password: ' . $result['message']
        );
    }

    private function panelAction(HostingAccount $hostingAccount, string $method, string $newStatus, string $successMessage): RedirectResponse
    {
        if (! $hostingAccount->serverModel || ! $hostingAccount->username) {
            return back()->with('error', 'Akun ini tidak terhubung ke server panel (dibuat manual), jadi tidak bisa dikontrol dari sini. Ubah status lewat form Edit.');
        }

        $result = HostingPanelFactory::make($hostingAccount->serverModel)->{$method}($hostingAccount->username);

        if ($result['success']) {
            $hostingAccount->update([
                'status' => $newStatus,
                'provision_status' => 'provisioned',
                'provision_message' => $result['message'],
            ]);

            return back()->with('success', $successMessage);
        }

        $hostingAccount->update(['provision_message' => $result['message']]);

        return back()->with('error', 'Gagal menghubungi server: ' . $result['message']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id'      => ['required', 'exists:clients,id'],
            'product_id'     => ['nullable', 'exists:products,id'],
            'server_id'      => ['nullable', 'exists:servers,id'],
            'domain'         => ['required', 'string', 'max:255'],
            'package'        => ['required', 'string', 'max:255'],
            'server'         => ['nullable', 'string', 'max:255'],
            'panel'          => ['required', 'in:cpanel,directadmin,plesk,vps'],
            'username'       => ['nullable', 'string', 'max:100'],
            'client_details' => ['nullable', 'string', 'max:5000'],
            'price'          => ['required', 'numeric', 'min:0'],
            'billing_cycle'  => ['required', 'in:monthly,quarterly,semi_annually,annually,custom'],
            'billing_mode'   => ['nullable', 'in:invoice,deposit'],
            'hourly_rate'    => ['nullable', 'numeric', 'min:0'],
            'status'         => ['required', 'in:pending,active,suspended,terminated'],
            'next_due_date'  => ['nullable', 'date'],
        ]);
    }

    /**
     * Tombol darurat untuk kasus invoice SUDAH lunas tapi provisioning
     * tidak pernah terpicu otomatis (mis. webhook gateway sempat
     * memanggil dua kali, dan pemicu otomatis cuma jalan saat status
     * BERUBAH jadi paid — kalau sudah paid lalu "paid" lagi, dianggap
     * tidak ada perubahan, jadi tidak memprovisikan apa pun).
     */
    public function retryProvisioning(HostingAccount $hostingAccount): RedirectResponse
    {
        $order = $hostingAccount->orders()->where('order_type', 'hosting')->latest('id')->first();

        if (! $order) {
            return back()->with('error', 'Order terkait hosting account ini tidak ditemukan — hubungi developer.');
        }

        $invoiceItem = \App\Models\InvoiceItem::where('order_id', $order->id)->first();

        if (! $invoiceItem) {
            return back()->with('error', 'Invoice terkait order ini tidak ditemukan — hubungi developer.');
        }

        if ($invoiceItem->invoice->status !== 'paid') {
            return back()->with('error', 'Invoice terkait belum lunas — provisioning cuma bisa dipicu untuk invoice yang sudah dibayar.');
        }

        app(\App\Services\Provisioning\ProvisioningService::class)->provisionInvoice($invoiceItem->invoice);

        $hostingAccount->refresh();

        return $hostingAccount->provision_status === 'provisioned'
            ? back()->with('success', 'Hosting berhasil diprovisikan.')
            : back()->with('error', 'Masih gagal: ' . ($hostingAccount->provision_message ?: 'Tidak diketahui — cek storage/logs/laravel.log.'));
    }

    /**
     * Untuk kasus akun SUDAH ada di server (kelihatan dari badge "Ada di
     * server" di halaman Diagnosa) tapi catatan kita masih 'manual' —
     * BUKAN mencoba createAccount() lagi (itu akan ditolak WHM sebagai
     * "akun sudah ada"), tapi membaca data akun yang sudah ada dan
     * menyesuaikan catatan kita supaya cocok.
     */
    public function syncFromServer(HostingAccount $hostingAccount): RedirectResponse
    {
        $server = $hostingAccount->serverModel;

        if (! $server) {
            return back()->with('error', 'Server tujuan tidak ditemukan.');
        }

        $service = HostingPanelFactory::make($server);

        if (! method_exists($service, 'listAccounts')) {
            return back()->with('error', 'Panel server ini belum mendukung sinkronisasi otomatis.');
        }

        $result = $service->listAccounts();

        if (! $result['success']) {
            return back()->with('error', 'Gagal mengambil daftar akun dari server: ' . $result['message']);
        }

        $match = collect($result['accounts'])->firstWhere('domain', $hostingAccount->domain);

        if (! $match) {
            return back()->with('error', "Domain {$hostingAccount->domain} tidak ditemukan di server ini — mungkin memang belum pernah dibuat. Coba \"Coba Provisikan\" biasa.");
        }

        $hostingAccount->update([
            'username'          => $match['username'],
            'status'            => $match['suspended'] ? 'suspended' : 'active',
            'provision_status'  => 'provisioned',
            'provision_message' => 'Disinkronkan dari server — akun ini ternyata sudah ada sebelumnya (bukan hasil provisioning baru).',
        ]);

        // Order terkait juga perlu ikut ditandai selesai -- provisioning
        // yang berhasil NORMAL selalu melakukan ini juga (lihat
        // ProvisioningService::provisionInvoice()), jadi disamakan di
        // sini supaya daftar Order tidak menggantung "Pending" selamanya
        // walau layanannya sendiri sudah aktif.
        $hostingAccount->orders()
            ->where('order_type', 'hosting')
            ->where('status', 'pending')
            ->update(['status' => 'active']);

        return back()->with('success', "Berhasil disinkronkan — username panel: {$match['username']}.");
    }
}
