<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HostingAccount;
use App\Models\Server;
use App\Services\Billing\HourlyRateCalculator;
use App\Services\Hosting\HostingPanelFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Menu khusus layanan VPS/VM -- terpisah dari Hosting Account biasa
 * karena cara kerjanya beda total: ditagih per jam dari saldo (bukan
 * invoice bulanan), spesifikasinya per-VM (bukan nama paket WHM), dan
 * hidup di provider cloud (bukan server cPanel).
 */
class VpsController extends Controller
{
    public function index(): View
    {
        // Server bertipe cloud -- untuk sekarang idcloudhost, tapi
        // sengaja pakai whereIn supaya provider cloud lain nanti
        // cukup ditambahkan ke daftar ini tanpa ubah query.
        $cloudServerIds = Server::whereIn('panel', ['idcloudhost'])->pluck('id');

        $accounts = HostingAccount::whereIn('server_id', $cloudServerIds)
            ->with(['client', 'serverModel'])
            ->latest()
            ->paginate(20);

        // Tarif dihitung per baris supaya tidak memanggil kalkulator
        // berulang kali di dalam view.
        $rates = [];
        foreach ($accounts as $account) {
            $rates[$account->id] = $this->rateFor($account);
        }

        $allActive = HostingAccount::whereIn('server_id', $cloudServerIds)->with('serverModel')->get();

        $stats = [
            'total'          => $allActive->count(),
            'active'         => $allActive->where('status', 'active')->count(),
            'deposit'        => $allActive->where('billing_mode', 'deposit')->count(),
            'hourly_revenue' => $allActive->where('status', 'active')->sum(fn ($a) => $this->rateFor($a) ?? 0),
        ];

        return view('admin.vps.index', compact('accounts', 'rates', 'stats'));
    }

    public function create(Request $request): View
    {
        $cloudServerIds = Server::whereIn('panel', ['idcloudhost'])->pluck('id');
        $servers = Server::whereIn('panel', ['idcloudhost'])->where('is_active', true)->orderBy('name')->get();

        // Pilihan OS/lokasi/kelas server ditarik langsung dari provider
        // supaya admin memilih dari daftar SUNGGUHAN, bukan mengetik
        // manual dan berisiko salah ketik (yang bikin provisioning gagal).
        $osImages = $locations = $pools = [];
        $limits = ['vcpu' => ['min' => 1, 'max' => 32], 'ram' => ['min' => 512, 'max' => 262144], 'disks' => ['min' => 20, 'max' => 1000]];
        $apiError = null;

        $server = $request->server_id
            ? $servers->firstWhere('id', (int) $request->server_id)
            : $servers->first();

        if ($server) {
            try {
                $service = new \App\Services\Hosting\IdCloudHostService($server);

                $img = $service->listVmImages();
                $osImages = $img['success'] ? ($img['raw'] ?? []) : [];

                $loc = $service->listLocations();
                $locations = $loc['success'] ? ($loc['raw'] ?? []) : [];

                $pool = $service->listHostPools();
                $pools = $pool['success'] ? ($pool['raw'] ?? []) : [];

                // Batasan spek SUNGGUHAN dari provider (mis. vCPU minimal
                // 2, bukan 1) -- dipakai membatasi isian form supaya
                // tidak mengirim spek yang pasti ditolak.
                $par = $service->getVmParameters();
                foreach (($par['success'] ? ($par['raw'] ?? []) : []) as $p) {
                    $key = $p['parameter'] ?? '';
                    if (isset($limits[$key])) {
                        $limits[$key] = ['min' => (int) ($p['min'] ?? $limits[$key]['min']), 'max' => (int) ($p['max'] ?? $limits[$key]['max'])];
                    }
                }

                if (! $img['success']) {
                    $apiError = $img['message'];
                }
            } catch (\Throwable $e) {
                $apiError = $e->getMessage();
            }
        }

        return view('admin.vps.create', [
            'servers'   => $servers,
            'clients'   => \App\Models\Client::orderBy('name')->get(),
            'products'  => \App\Models\Product::whereIn('server_id', $cloudServerIds)->where('is_active', true)->orderBy('name')->get(),
            'osImages'  => $osImages,
            'locations' => $locations,
            'pools'     => $pools,
            'limits'    => $limits,
            'apiError'  => $apiError,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id'     => ['required', 'exists:clients,id'],
            'server_id'     => ['required', 'exists:servers,id'],
            'domain'        => ['required', 'string', 'max:255', 'regex:/^[0-9a-zA-Z][-0-9a-zA-Z]*[0-9a-zA-Z]$/'],
            'vcpu'          => ['required', 'integer', 'min:1', 'max:16'],
            'ram'           => ['required', 'integer', 'min:512'],
            'disk'          => ['required', 'integer', 'min:20'],
            'os_name'       => ['required', 'string', 'max:50'],
            'os_version'    => ['required', 'string', 'max:50'],
            'username'      => ['required', 'string', 'max:50'],
            'password'      => ['required', 'string', 'min:8'],
            'billing_mode'  => ['required', 'in:deposit,invoice'],
            'price'         => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['nullable', 'in:monthly,quarterly,semi_annually,annually'],
            'location'      => ['nullable', 'string', 'max:20'],
            'pool_uuid'     => ['nullable', 'string', 'max:64'],
        ], [
            'domain.regex' => 'Nama VM hanya boleh huruf, angka, dan strip — tidak boleh diawali/diakhiri strip.',
        ]);

        $server = Server::findOrFail($data['server_id']);

        // Isian ramah pengguna dipadatkan jadi JSON spek -- format yang
        // dibaca IdCloudHostService saat membuat VM, dan
        // HourlyRateCalculator saat menghitung tagihan per jam.
        $spec = json_encode([
            'vcpu'           => $data['vcpu'],
            'ram'            => $data['ram'],
            'disk'           => $data['disk'],
            'os_name'        => $data['os_name'],
            'os_version'     => $data['os_version'],
            'backup_enabled' => $request->boolean('backup_enabled'),
            'location'       => $data['location'] ?? null,
            'pool_uuid'      => $data['pool_uuid'] ?? null,
        ]);

        $account = HostingAccount::create([
            'client_id'        => $data['client_id'],
            'server_id'        => $server->id,
            'domain'           => $data['domain'],
            'package'          => $spec,
            'panel'            => $server->panel,
            'price'            => $data['billing_mode'] === 'invoice' ? ($data['price'] ?? 0) : 0,
            'billing_cycle'    => $data['billing_cycle'] ?? 'monthly',
            'billing_mode'     => $data['billing_mode'],
            'status'           => 'pending',
            'provision_status' => 'manual',
            'next_due_date'    => now()->addMonth(),
        ]);

        if (! $request->boolean('provision_now')) {
            return redirect()->route('admin.vps')->with('success', 'VPS dicatat tanpa provisioning. Buat VM-nya manual bila perlu.');
        }

        try {
            $result = HostingPanelFactory::make($server)->createAccount([
                'domain'   => $data['domain'],
                'username' => $data['username'],
                'password' => $data['password'],
                'package'  => $spec,
                'email'    => $account->client->email ?? null,
            ]);
        } catch (\Throwable $e) {
            $account->update(['provision_status' => 'failed', 'provision_message' => $e->getMessage()]);

            return redirect()->route('admin.vps')->with('error', 'VPS tercatat, tapi pembuatan VM gagal: ' . $e->getMessage());
        }

        $updates = [
            'provision_status'  => $result['success'] ? 'provisioned' : 'failed',
            'provision_message' => $result['message'],
        ];

        if ($result['success']) {
            $updates['status'] = 'active';
            // UUID VM WAJIB disimpan -- itu pengenal untuk semua operasi
            // berikutnya (start/stop/hapus/suspend otomatis).
            $updates['username'] = $result['username'] ?? $data['username'];
            $updates['last_billed_at'] = now();

            $updates['client_details'] = trim(
                "IP Server: " . ($result['ip'] ?? '(menyusul, cek Diagnosa)') . "\n"
                . "Username: {$data['username']}\n"
                . "Password: {$data['password']}"
            );
        }

        $account->update($updates);

        return redirect()->route('admin.vps')->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? "VPS {$data['domain']} berhasil dibuat." : 'Pembuatan VM gagal: ' . $result['message']
        );
    }

    /**
     * Sama logikanya dengan ChargeHourlyUsage::effectiveRate() --
     * hitung dari kartu harga server kalau ada spek VM, kalau tidak
     * pakai tarif flat manual.
     */
    /**
     * Coba buat ulang VM untuk layanan yang provisioning-nya gagal.
     * Berguna setelah memperbaiki penyebabnya (mis. Billing Account ID
     * salah) -- tidak perlu hapus lalu buat record baru dari awal.
     */
    public function retry(Request $request, HostingAccount $vps): RedirectResponse
    {
        if ($vps->provision_status === 'provisioned') {
            return back()->with('error', 'VPS ini sudah pernah berhasil dibuat — jangan dibuat ulang agar tidak terbentuk VM ganda.');
        }

        if (! $vps->serverModel) {
            return back()->with('error', 'VPS ini tidak terhubung ke server manapun.');
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
            'vcpu'     => ['nullable', 'integer', 'min:1'],
            'ram'      => ['nullable', 'integer', 'min:512'],
            'disk'     => ['nullable', 'integer', 'min:10'],
        ]);

        // Spek bisa dikoreksi saat mencoba ulang -- percuma mengulang
        // dengan spek yang sama kalau penyebab gagalnya justru speknya
        // (mis. provider menolak 1 vCPU karena minimalnya 2).
        if (! empty($data['vcpu'])) {
            $spec = json_decode((string) $vps->package, true) ?: [];
            $spec['vcpu'] = (int) $data['vcpu'];
            $spec['ram'] = (int) ($data['ram'] ?? $spec['ram'] ?? 1024);
            $spec['disk'] = (int) ($data['disk'] ?? $spec['disk'] ?? 20);

            $vps->update(['package' => json_encode($spec)]);
            $vps->refresh();
        }

        try {
            $result = HostingPanelFactory::make($vps->serverModel)->createAccount([
                'domain'   => $vps->domain,
                'username' => $data['username'],
                'password' => $data['password'],
                'package'  => $vps->package,
                'email'    => $vps->client->email ?? null,
            ]);
        } catch (\Throwable $e) {
            $vps->update(['provision_status' => 'failed', 'provision_message' => $e->getMessage()]);

            return back()->with('error', 'Gagal: ' . $e->getMessage());
        }

        if (! $result['success']) {
            $vps->update(['provision_status' => 'failed', 'provision_message' => $result['message']]);

            return back()->with('error', 'Provider menolak: ' . $result['message']);
        }

        // Kalau ternyata VM-nya diadopsi (sudah ada di provider), spek
        // yang tersimpan HARUS mengikuti VM sungguhan -- bukan angka
        // yang diketik di form. Kalau tidak, tagihan ke klien dihitung
        // dari spek yang tidak sesuai mesin aslinya.
        $raw = $result['raw'] ?? [];

        if (! empty($raw['vcpu'])) {
            $spec = json_decode((string) $vps->package, true) ?: [];
            $diskAsli = collect($raw['storage'] ?? [])->firstWhere('primary', true)['size'] ?? ($spec['disk'] ?? 20);

            $spec['vcpu'] = (int) $raw['vcpu'];
            $spec['ram'] = (int) ($raw['memory'] ?? $spec['ram'] ?? 1024);
            $spec['disk'] = (int) $diskAsli;
            $spec['os_name'] = $raw['os_name'] ?? ($spec['os_name'] ?? '');
            $spec['os_version'] = $raw['os_version'] ?? ($spec['os_version'] ?? '');

            $vps->update(['package' => json_encode($spec)]);
        }

        $vps->update([
            'status'            => 'active',
            'provision_status'  => 'provisioned',
            'provision_message' => $result['message'],
            'username'          => $result['username'] ?? $data['username'],
            'last_billed_at'    => now(),
            'client_details'    => trim(
                'IP Server: ' . ($result['ip'] ?? '(menyusul, cek Diagnosa)') . "\n"
                . "Username: {$data['username']}\n"
                . "Password: {$data['password']}"
            ),
        ]);

        return back()->with('success', "VPS {$vps->domain} berhasil dibuat.");
    }

    /**
     * Hapus catatan VPS. Ada dua tingkat:
     * - hapus_vm=1  : VM di provider DIHAPUS PERMANEN, lalu catatan dihapus.
     * - hapus_vm=0  : hanya catatan di sistem ini yang dihapus, VM di
     *                 provider dibiarkan (untuk kasus VM sudah dihapus
     *                 manual, atau mau dilepas dari billing tanpa merusak
     *                 mesin yang masih dipakai).
     */
    public function destroy(Request $request, HostingAccount $vps): RedirectResponse
    {
        $hapusVm = $request->boolean('hapus_vm');

        if ($hapusVm && $vps->serverModel && $vps->username && $vps->provision_status === 'provisioned') {
            try {
                $result = HostingPanelFactory::make($vps->serverModel)->terminateAccount($vps->username);

                if (! $result['success']) {
                    return back()->with('error', 'VM gagal dihapus di provider: ' . $result['message']
                        . ' — catatan TIDAK dihapus agar tidak ada VM yatim yang tetap menagih biaya.');
                }
            } catch (\Throwable $e) {
                return back()->with('error', 'VM gagal dihapus: ' . $e->getMessage());
            }
        }

        $nama = $vps->domain;
        $vps->delete();

        return redirect()->route('admin.vps')->with('success', $hapusVm
            ? "VPS {$nama} dan VM-nya sudah dihapus."
            : "Catatan {$nama} dihapus. VM di provider TIDAK disentuh.");
    }

    public function power(Request $request, HostingAccount $vps): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', 'in:start,stop']])['action'];

        if (! $vps->serverModel || ! $vps->username) {
            return back()->with('error', 'VPS ini belum terhubung ke provider.');
        }

        try {
            $service = HostingPanelFactory::make($vps->serverModel);
            $result = $action === 'start'
                ? $service->unsuspendAccount($vps->username)
                : $service->suspendAccount($vps->username);
        } catch (\Throwable $e) {
            return back()->with('error', 'Perintah gagal: ' . $e->getMessage());
        }

        if (! $result['success']) {
            return back()->with('error', 'Provider menolak: ' . $result['message']);
        }

        $vps->update($action === 'start'
            ? ['status' => 'active', 'last_billed_at' => now()]
            : ['status' => 'suspended']);

        return back()->with('success', $action === 'start' ? 'VPS sedang dinyalakan.' : 'VPS sedang dimatikan.');
    }

    /**
     * Pasang IP publik ke VM. VM di IDCloudHost hanya dapat IP privat
     * (10.x.x.x) saat dibuat -- tanpa IP publik, VPS TIDAK BISA diakses
     * dari internet sama sekali.
     */
    public function attachIp(HostingAccount $vps): RedirectResponse
    {
        if (! $vps->serverModel || ! $vps->username || $vps->provision_status !== 'provisioned') {
            return back()->with('error', 'VM belum terbentuk — pasang IP setelah VM berhasil dibuat.');
        }

        try {
            $service = new \App\Services\Hosting\IdCloudHostService($vps->serverModel);

            // Cek dulu: kalau VM sudah punya IP publik, jangan
            // alokasikan lagi -- tiap IP menagih biaya sendiri.
            $info = $service->getVmInfo($vps->username);

            if ($info['success'] && ! empty($info['raw']['public_ipv4'])) {
                return back()->with('error', "VM ini sudah punya IP publik: {$info['raw']['public_ipv4']} — tidak perlu dipasang lagi.");
            }

            $result = $service->attachPublicIp($vps->username, $vps->domain);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memasang IP: ' . $e->getMessage());
        }

        if (! $result['success']) {
            return back()->with('error', 'Gagal memasang IP publik: ' . $result['message']);
        }

        // Catatan akses klien diperbarui supaya IP barunya langsung
        // terlihat di dashboard mereka tanpa perlu diedit manual.
        if (! empty($result['address'])) {
            $detail = (string) $vps->client_details;

            $vps->update([
                'client_details' => preg_match('/^IP Server:.*$/m', $detail)
                    ? preg_replace('/^IP Server:.*$/m', "IP Server: {$result['address']}", $detail)
                    : trim("IP Server: {$result['address']}\n" . $detail),
            ]);
        }

        return back()->with('success', $result['message'] ?? 'IP publik berhasil dipasang.');
    }

    private function rateFor(HostingAccount $account): ?float
    {
        if ($account->serverModel && $account->hasVmSpec()) {
            $rate = HourlyRateCalculator::calculate($account->serverModel, $account->vmSpec());

            if ($rate > 0) {
                return $rate;
            }
        }

        return $account->hourly_rate ? (float) $account->hourly_rate : null;
    }
}
