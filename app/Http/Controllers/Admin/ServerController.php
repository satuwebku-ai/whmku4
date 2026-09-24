<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Hosting\HostingPanelFactory;
use App\Services\Vps\VpsProviderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServerController extends Controller
{
    public function index(): View
    {
        $servers = Server::withCount('hostingAccounts')->latest()->paginate(10);

        return view('admin.servers.index', compact('servers'));
    }

    public function indexBootstrap(): View
    {
        return $this->index();
    }

    public function create(): View
    {
        return view('admin.servers.form', ['server' => new Server()]);
    }

    public function createBootstrap(): View
    {
        return $this->create();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['hostname'] = $data['hostname'] ?? '';
        $data['api_username'] = $data['api_username'] ?? '';
        $data['verify_ssl'] = $request->boolean('verify_ssl');
        $data['is_active'] = $request->boolean('is_active', true);

        Server::create($data);

        return redirect()->route('admin.servers.index')->with('success', 'Server berhasil ditambahkan.');
    }

    public function edit(Server $server): View
    {
        return view('admin.servers.form', compact('server'));
    }

    public function editBootstrap(Server $server): View
    {
        return $this->edit($server);
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $data = $this->validated($request, updating: true);
        $data['hostname'] = $data['hostname'] ?? '';
        $data['api_username'] = $data['api_username'] ?? '';
        $data['verify_ssl'] = $request->boolean('verify_ssl');
        $data['is_active'] = $request->boolean('is_active');

        // Kalau field token dikosongkan saat edit, jangan timpa token yang sudah tersimpan.
        if (empty($data['api_token'])) {
            unset($data['api_token']);
        }

        $server->update($data);

        return redirect()->route('admin.servers.index')->with('success', 'Server berhasil diperbarui.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        if ($server->hostingAccounts()->exists()) {
            return back()->with('error', 'Server tidak bisa dihapus karena masih punya hosting account terhubung.');
        }

        $server->delete();

        return redirect()->route('admin.servers.index')->with('success', 'Server berhasil dihapus.');
    }

    public function testConnection(Server $server): RedirectResponse
    {
        $result = $server->isCloud()
            ? VpsProviderFactory::make($server)->testConnection()
            : HostingPanelFactory::make($server)->testConnection();

        $server->update([
            'last_checked_at' => now(),
            'last_check_status' => $result['success'] ? 'ok' : $result['message'],
        ]);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Koneksi ke server berhasil.' : 'Koneksi gagal: ' . $result['message']
        );
    }

    /**
     * Login sekali klik ke WHM server ini, tanpa perlu masukkan
     * username/password manual -- pakai API Token yang sudah tersimpan.
     */
    public function loginWhm(Server $server): RedirectResponse
    {
        if ($server->isCloud()) {
            return back()->with('error', 'Server VM/VPS tidak punya WHM.');
        }

        $panel = HostingPanelFactory::make($server);

        if (! method_exists($panel, 'createWhmSsoSession')) {
            return back()->with('error', 'Panel ' . $server->panel . ' belum mendukung login sekali klik ke WHM.');
        }

        $result = $panel->createWhmSsoSession();

        if (! $result['success']) {
            return back()->with('error', 'Gagal membuat sesi login WHM: ' . $result['message']);
        }

        return redirect()->away($result['url']);
    }

    /**
     * Bandingkan nama panel_package yang diketik di form Produk dengan
     * paket yang BENAR-BENAR ada di server — sumber error paling sering
     * saat provisioning otomatis gagal diam-diam.
     */
    public function diagnostics(Server $server): View|RedirectResponse
    {
        if ($server->isCloud()) {
            // Diagnosa detail (daftar VM, OS, harga modal) baru ada untuk
            // IDCloudHost. Provider lain: cukup Tes Koneksi.
            if ($server->vpsDriver() !== 'idcloudhost') {
                return redirect()->route('admin.servers.index')
                    ->with('error', 'Diagnosa detail belum tersedia untuk ' . $server->vpsLabel() . '. Gunakan Tes Koneksi.');
            }

            return view('admin.servers.diagnostics-idcloudhost', $this->idCloudHostDiagnosticsData($server));
        }

        return view('admin.servers.diagnostics', $this->diagnosticsData($server));
    }

    public function diagnosticsBootstrap(Server $server): View|RedirectResponse
    {
        return $this->diagnostics($server);
    }

    /**
     * Diagnosa server hosting biasa (cPanel/DirectAdmin/Plesk). Server
     * VM/VPS tidak lewat sini -- lihat diagnostics().
     */
    private function diagnosticsData(Server $server): array
    {
        $service = HostingPanelFactory::make($server);

        $packages = [];
        $apiError = null;

        if (method_exists($service, 'listPackages')) {
            try {
                $result = $service->listPackages();
                $packages = $result['success'] ? $result['packages'] : [];

                if (! $result['success']) {
                    $apiError = $result['message'];
                }
            } catch (\Throwable $e) {
                $apiError = $e->getMessage();
            }
        } else {
            $apiError = 'Panel ' . ucfirst($server->panel) . ' belum mendukung daftar paket lewat sistem.';
        }

        // Produk yang menunjuk ke server ini, dan apakah panel_package-nya
        // benar-benar ada di daftar paket sungguhan di atas.
        $products = \App\Models\Product::where('server_id', $server->id)
            ->where('is_active', true)
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'panel_package' => $p->panel_package,
                'matches' => blank($p->panel_package) ? null : in_array($p->panel_package, $packages, true),
            ]);

        // Bandingkan catatan Hosting Account kita dengan akun yang
        // BENAR-BENAR ada di server — supaya kelihatan kalau ada yang
        // "menurut kita ada, tapi sebenarnya tidak pernah dibuat", atau
        // sebaliknya (ada di server tapi tidak tercatat di sistem kita).
        $whmDomains = [];
        $accountsError = null;

        if (method_exists($service, 'listAccounts')) {
            try {
                $result = $service->listAccounts();

                if ($result['success']) {
                    $whmDomains = array_column($result['accounts'], 'domain');
                } else {
                    $accountsError = $result['message'];
                }
            } catch (\Throwable $e) {
                $accountsError = $e->getMessage();
            }
        }

        $ourAccounts = \App\Models\HostingAccount::where('server_id', $server->id)
            ->get(['id', 'domain', 'status', 'provision_status'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'domain' => $a->domain,
                'status' => $a->status,
                'provision_status' => $a->provision_status,
                'ada_di_whm' => in_array($a->domain, $whmDomains, true),
            ]);

        // Akun yang ada di server tapi TIDAK tercatat di sistem kita sama
        // sekali — biasanya dibuat manual langsung di WHM, di luar Lumora.
        $orphanWhmDomains = array_diff($whmDomains, $ourAccounts->pluck('domain')->all());

        return compact('server', 'packages', 'apiError', 'products', 'ourAccounts', 'orphanWhmDomains', 'accountsError');
    }

    /**
     * Tarik harga modal terbaru dari provider (lewat pricing() milik adapter
     * provider-nya) lalu simpan ke cost_cache. Dipakai mode markup -- harga
     * jual dihitung dari angka ini, jadi perlu disegarkan sesekali (atau
     * setiap kali provider mengubah harga). Hanya membaca, tidak mengubah
     * apa pun di provider.
     */
    public function syncCost(Server $server): RedirectResponse
    {
        if (! $server->isCloud() || ! config('vps_providers.' . $server->vpsDriver() . '.cost_sync', false)) {
            return back()->with('error', 'Tarik harga modal belum tersedia untuk provider ini.');
        }

        try {
            $result = VpsProviderFactory::make($server)->pricing();
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menarik harga modal: ' . $e->getMessage());
        }

        if (! $result['success']) {
            return back()->with('error', 'Gagal menarik harga modal: ' . $result['message']);
        }

        $server->update(['cost_cache' => $result['cost'], 'cost_cached_at' => now()]);

        $note = ($result['cost']['currency'] ?? 'IDR') !== 'IDR' && ! $server->costFxRate()
            ? ' Isi kurs ke Rupiah di form Server supaya harga modal bisa dipakai.'
            : '';

        return back()->with('success', 'Harga modal berhasil disegarkan dari ' . $server->vpsLabel() . '.' . $note);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        // Jenis Panel "vps" = server cloud: provider-nya dipilih di
        // "VPS Provider", dan field yang wajib mengikuti profil provider itu
        // di config/vps_providers.php (mis. IDCloudHost: hostname = slug
        // lokasi & api_username = billing account id, keduanya opsional;
        // DigitalOcean: cuma API Token). Server hosting biasa (cPanel dst)
        // tetap wajib Hostname, Port, dan API Username.
        $isVps = $request->input('panel') === 'vps';
        $fields = $isVps ? (array) config('vps_providers.' . $request->input('vps_provider') . '.fields', []) : [];
        $required = fn (string $field) => $isVps ? (bool) ($fields[$field]['required'] ?? false) : true;

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'hostname'     => [$required('hostname') ? 'required' : 'nullable', 'string', 'max:255'],
            'ns1'          => ['nullable', 'string', 'max:255'],
            'ns2'          => ['nullable', 'string', 'max:255'],
            'port'         => [$isVps ? 'nullable' : 'required', 'integer', 'min:1', 'max:65535'],
            'panel'        => ['required', Rule::in(['cpanel', 'directadmin', 'plesk', 'vps'])],
            'vps_provider' => [Rule::requiredIf($isVps), 'nullable', 'string', Rule::in(array_keys(config('vps_providers', [])))],
            'api_username' => [$required('api_username') ? 'required' : 'nullable', 'string', 'max:100'],
            'api_token'    => [$updating ? 'nullable' : 'required', 'string'],
            'verify_ssl'   => ['nullable', 'boolean'],
            'max_accounts' => ['nullable', 'integer', 'min:1'],
            'price_per_vcpu_hour' => ['nullable', 'numeric', 'min:0'],
            'price_per_ram_gb_hour' => ['nullable', 'numeric', 'min:0'],
            'price_per_storage_gb_hour' => ['nullable', 'numeric', 'min:0'],
            'price_per_backup_gb_hour' => ['nullable', 'numeric', 'min:0'],
            'price_per_snapshot_gb_hour' => ['nullable', 'numeric', 'min:0'],
            'price_windows_license_per_vcpu_hour' => ['nullable', 'numeric', 'min:0'],
            'pricing_mode' => ['nullable', 'in:manual,markup'],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'cost_fx_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active'    => ['nullable', 'boolean'],
        ], [
            'vps_provider.required' => 'Pilih VPS Provider dulu.',
            'vps_provider.in'       => 'VPS Provider tidak dikenali.',
        ]);

        if ($isVps) {
            // Nameserver & port tidak berlaku untuk server VPS. Port tidak
            // diisi supaya kolomnya tetap memakai nilai bawaan/lama.
            $data['ns1'] = null;
            $data['ns2'] = null;
            unset($data['port']);
        } else {
            $data['vps_provider'] = null;
        }

        return $data;
    }

    /**
     * Data Diagnosa khusus IDCloudHost -- daftar VM sungguhan di
     * akun/lokasi ini, plus daftar OS yang bisa dipilih saat membuat
     * produk VPS baru. Dibuat terpisah dari diagnosticsData() karena
     * "paket" & "akun" ala cPanel tidak berlaku untuk provider ini.
     */
    private function idCloudHostDiagnosticsData(Server $server): array
    {
        $service = new \App\Services\Hosting\IdCloudHostService($server);

        // Tiap bagian dipanggil terpisah & errornya disimpan sendiri --
        // supaya satu endpoint yang gagal tidak membuat SELURUH halaman
        // Diagnosa kosong (mis. endpoint kredit yang belum pasti).
        $sections = [];

        foreach ([
            'user'       => fn () => $service->getUserInfo(),
            'billing'    => fn () => $service->listBillingAccounts(),
            'pricing'    => fn () => $service->getPricingPolicy(),
            'locations'  => fn () => $service->listLocations(),
            'pools'      => fn () => $service->listHostPools(),
            'params'     => fn () => $service->getVmParameters(),
            'images'     => fn () => $service->listVmImages(),
            'appCatalog' => fn () => $service->listAppCatalogImages(),
            'bootImages' => fn () => $service->listBootImages(),
            'vms'        => fn () => $service->listVms(),
            'disks'      => fn () => $service->listDisks(),
            'ips'        => fn () => $service->listFloatingIps(),
            'networks'   => fn () => $service->listNetworks(),
        ] as $key => $fetch) {
            try {
                $result = $fetch();
                $sections[$key] = [
                    'data'  => $result['success'] ? ($result['raw'] ?? []) : null,
                    'error' => $result['success'] ? null : $result['message'],
                ];
            } catch (\Throwable $e) {
                $sections[$key] = ['data' => null, 'error' => $e->getMessage()];
            }
        }

        // Billing account yang dipakai. Kolom api_username idealnya diisi
        // ID angka (mis. 1200206137), TAPI mudah keliru diisi judul akun
        // -- jadi dicoba bertahap: cocokkan ID, lalu judul, dan kalau
        // tetap tidak ketemu pakai akun default supaya halaman tetap
        // berguna alih-alih kosong sama sekali.
        $billingAccount = null;
        if (is_array($sections['billing']['data'] ?? null)) {
            $accounts = collect($sections['billing']['data']);
            $wanted = trim((string) $server->api_username);

            if ($wanted !== '') {
                $billingAccount = $accounts->firstWhere('id', (int) $wanted)
                    ?? $accounts->first(fn ($a) => strcasecmp(trim($a['title'] ?? ''), $wanted) === 0);
            }

            $billingAccount ??= $accounts->firstWhere('is_default', true) ?? $accounts->first();
        }

        // Pemakaian bulan berjalan -- butuh billing_account_id, jadi
        // baru bisa diambil setelah akunnya ketahuan di atas.
        $sections['usage'] = ['data' => null, 'error' => 'Billing account belum diketahui.'];
        if ($billingAccount && isset($billingAccount['id'])) {
            try {
                $usage = $service->getResourceUsage($billingAccount['id']);
                $sections['usage'] = [
                    'data'  => $usage['success'] ? ($usage['raw'] ?? []) : null,
                    'error' => $usage['success'] ? null : $usage['message'],
                ];
            } catch (\Throwable $e) {
                $sections['usage'] = ['data' => null, 'error' => $e->getMessage()];
            }
        }

        $products = \App\Models\Product::where('server_id', $server->id)
            ->where('is_active', true)
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'spec' => json_decode((string) $p->panel_package, true),
            ]);

        // Harga modal dari /pricing/policy.
        //
        // DUA HAL PENTING yang berbeda dari dokumentasi (dikonfirmasi
        // dari respons API sungguhan 26 Agu 2026):
        // 1. Nama fieldnya "pricePerUnit", BUKAN "price" seperti di docs.
        // 2. numCpus / megsRam / gigsStorage itu AMBANG TINGKATAN harga,
        //    bukan jumlah untuk dibagi. Contoh: CPU 1-2 unit = 25,685/jam
        //    per CPU, tapi 3+ unit = 51,37/jam per CPU (dobel). Storage
        //    juga dobel di atas 81 GB. Jadi harga modal SUNGGUHAN
        //    tergantung ukuran VM-nya.
        $tiers = ['cpu' => [], 'ram' => [], 'main' => [], 'backup' => [], 'snapshot' => []];
        $costWindows = null;

        foreach (($sections['pricing']['data']['policy'] ?? []) as $policy) {
            $price = (float) ($policy['pricePerUnit'] ?? $policy['price'] ?? 0);
            $type = $policy['resourceType'] ?? '';
            $service = $policy['serviceNameInUptime'] ?? '';

            if ($type === 'CPU') {
                $tiers['cpu'][] = ['from' => (int) ($policy['numCpus'] ?? 0), 'price' => $price];
            } elseif ($type === 'RAM') {
                $tiers['ram'][] = ['from' => (float) ($policy['megsRam'] ?? 0) / 1024, 'price' => $price];
            } elseif ($type === 'STORAGE' && isset($tiers[$service])) {
                $tiers[$service][] = ['from' => (int) ($policy['gigsStorage'] ?? 0), 'price' => $price];
            } elseif ($type === 'LICENSE' && $service === 'windows') {
                $costWindows = $price;
            }
        }

        // Untuk tabel perbandingan dipakai tingkat TERENDAH (paling umum
        // dipakai), plus catatan tingkat berikutnya supaya admin sadar
        // harganya naik untuk VM besar.
        $lowest = function (array $list) {
            if (! $list) return [null, null];
            usort($list, fn ($a, $b) => $a['from'] <=> $b['from']);
            $next = count($list) > 1 ? $list[1] : null;

            return [$list[0]['price'], $next];
        };

        [$costPerVcpu, $nextCpu]       = $lowest($tiers['cpu']);
        [$costPerRamGb, $nextRam]      = $lowest($tiers['ram']);
        [$costStorage, $nextStorage]   = $lowest($tiers['main']);
        [$costBackup, $nextBackup]     = $lowest($tiers['backup']);
        [$costSnapshot, $nextSnapshot] = $lowest($tiers['snapshot']);

        // Harga jual diambil lewat effectiveRates() -- BUKAN langsung
        // dari kolom manual -- supaya mode Markup ikut terbaca. Sempat
        // keliru membaca kolom manual saja, sehingga server bermode
        // markup selalu tampil "belum diisi" padahal sudah diatur.
        $sell = \App\Services\Billing\HourlyRateCalculator::effectiveRates($server);

        $rateCard = [
            'vCPU (per unit)' => ['jual' => $sell['vcpu'] ?: null, 'modal' => $costPerVcpu,
                'tier' => $nextCpu ? "{$nextCpu['from']}+ vCPU: " . number_format($nextCpu['price'], 3) : null],
            'RAM (per GB)' => ['jual' => $sell['ram'] ?: null, 'modal' => $costPerRamGb,
                'tier' => $nextRam ? "{$nextRam['from']}+ GB: " . number_format($nextRam['price'], 3) : null],
            'Storage (per GB)' => ['jual' => $sell['storage'] ?: null, 'modal' => $costStorage,
                'tier' => $nextStorage ? "{$nextStorage['from']}+ GB: " . number_format($nextStorage['price'], 3) : null],
            'Backup (per GB)' => ['jual' => $sell['backup'] ?: null, 'modal' => $costBackup,
                'tier' => $nextBackup ? "{$nextBackup['from']}+ GB: " . number_format($nextBackup['price'], 3) : null],
            'Snapshot (per GB)' => ['jual' => $sell['snapshot'] ?: null, 'modal' => $costSnapshot,
                'tier' => $nextSnapshot ? "{$nextSnapshot['from']}+ GB: " . number_format($nextSnapshot['price'], 3) : null],
            'Lisensi Windows (/vCPU)' => ['jual' => $sell['windows'] ?: null, 'modal' => $costWindows, 'tier' => null],
        ];

        return compact('server', 'sections', 'products', 'rateCard', 'billingAccount');
    }
}
