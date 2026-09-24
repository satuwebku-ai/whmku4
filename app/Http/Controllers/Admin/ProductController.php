<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Services\Billing\HourlyRateCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.products.index', $this->indexData($request));
    }

    public function indexBootstrap(Request $request): View
    {
        return view('admin.products.index', $this->indexData($request));
    }

    private function indexData(Request $request): array
    {
        // Produk VPS dibedakan dari produk hosting biasa lewat
        // Product::scopeVpsType()/scopeHostingType() -- kategori
        // (product_groups.type='vps') sebagai sumber utama, server cloud
        // sebagai jaring pengaman. Query yang sama dipakai di beranda
        // publik (CatalogController) supaya sebuah produk tidak bisa
        // dianggap "hosting" di satu tempat dan "VPS" di tempat lain.
        $cloudServerIds = Server::cloud()->pluck('id');

        $products = Product::with('category')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn ($q) => $q->where('product_category_id', $request->category_id))
            ->when($request->type === 'vps', fn ($q) => $q->vpsType())
            ->when($request->type === 'hosting', fn ($q) => $q->hostingType())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = ProductGroup::orderBy('name')->get();

        $counts = [
            'all'     => Product::count(),
            'vps'     => Product::vpsType()->count(),
            'hosting' => Product::hostingType()->count(),
        ];

        return compact('products', 'categories', 'counts', 'cloudServerIds');
    }

    public function create(): View
    {
        return $this->formView(new Product());
    }

    public function createBootstrap(): View
    {
        return $this->create();
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $warnings] = $this->preparedData($request);

        Product::create($data);

        return $this->savedRedirect('Produk berhasil dibuat.', $warnings);
    }

    public function edit(Product $product): View
    {
        return $this->formView($product);
    }

    public function editBootstrap(Product $product): View
    {
        return $this->edit($product);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        [$data, $warnings] = $this->preparedData($request, $product->id);

        $product->update($data);

        return $this->savedRedirect('Produk berhasil diperbarui.', $warnings);
    }

    /**
     * Data siap simpan untuk store()/update(): validasi, mode tagihan,
     * spesifikasi VPS (panel_package), cek harga, dan peringatan harga
     * jual di bawah harga modal provider.
     *
     * @return array{0: array, 1: string[]}
     */
    private function preparedData(Request $request, ?int $ignoreId = null): array
    {
        $data = $this->validated($request, $ignoreId);
        $this->assertCategoryMatchesServer($data);
        $data['billing_mode'] = $this->normalizedBillingMode($data);
        $data = $this->withVmSpec($request, $data);
        $this->assertHasPrice($data);
        $warnings = $this->pricingWarnings($data);

        return [$this->withExtras($request, $data), $warnings];
    }

    /**
     * Kategori VPS (product_groups.type='vps') WAJIB dipasangkan dengan
     * server cloud. Tanpa ini, produk yang tampak VPS di menu/URL publik
     * (kategorinya) tapi tidak punya server cloud akan nyasar tampil di
     * bagian "Paket Hosting Pilihan" halaman depan (lihat
     * Product::scopeHostingType()) dan tidak dapat spek/harga modal VPS.
     *
     * @throws ValidationException
     */
    private function assertCategoryMatchesServer(array $data): void
    {
        $isVpsCategory = ProductGroup::where('id', $data['product_category_id'])->where('type', 'vps')->exists();
        $isCloudServer = ! empty($data['server_id']) && Server::cloud()->whereKey($data['server_id'])->exists();

        if ($isVpsCategory && ! $isCloudServer) {
            throw ValidationException::withMessages(['server_id' => 'Kategori ini bertipe VPS — pilih server bertipe VM/VPS, bukan server hosting biasa.']);
        }

        if (! $isVpsCategory && $isCloudServer) {
            throw ValidationException::withMessages(['product_category_id' => 'Server ini bertipe VM/VPS — pilih kategori produk bertipe VPS, bukan Hosting.']);
        }
    }

    private function savedRedirect(string $message, array $warnings): RedirectResponse
    {
        $redirect = redirect()->route('admin.products.index')->with('success', $message);

        return $warnings ? $redirect->with('warning', implode(' ', $warnings)) : $redirect;
    }

    private function formView(Product $product): View
    {
        $servers = Server::where('is_active', true)->orderBy('name')->get();

        return view('admin.products.form', [
            'product' => $product,
            'categories' => ProductGroup::orderBy('name')->get(),
            'servers' => $servers,
            'vpsServerMeta' => $this->vpsServerMeta($servers),
        ]);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil dihapus.');
    }

    public function status(Request $request): RedirectResponse
    {
        $product = Product::findOrFail($request->input('product_id'));
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', "Produk {$product->name} berhasil " . ($product->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    /**
     * Produk tanpa satupun harga siklus terisi tidak bisa dibeli — tolak
     * sebelum tersimpan, bukan biarkan muncul rusak di katalog.
     */
    private function assertHasPrice(array $data): void
    {
        // Produk billing_mode='deposit' (VPS Pay As You Grow) tidak
        // ditagih lewat harga siklus sama sekali -- tarifnya dari kartu
        // harga SERVER (lihat HourlyRateCalculator), jadi wajib-isi-satu-
        // harga tidak relevan untuk produk ini.
        if (($data['billing_mode'] ?? 'invoice') === 'deposit') {
            return;
        }

        $hasPrice = collect(['price_monthly', 'price_quarterly', 'price_semi_annually', 'price_annually', 'price_custom'])
            ->contains(fn ($key) => filled($data[$key] ?? null));

        if (! $hasPrice) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'price_monthly' => 'Isi minimal satu harga siklus tagihan (bulanan/3 bulan/6 bulan/tahunan).',
            ]);
        }
    }

    /**
     * billing_mode='deposit' cuma valid untuk produk yang server tujuannya
     * memang server cloud/VPS. Dicek DI SINI (bukan cuma disembunyikan &
     * dinonaktifkan di form) supaya request langsung yang mengakali field
     * tidak bisa membuat produk hosting biasa diam-diam jadi "deposit".
     */
    private function normalizedBillingMode(array $data): string
    {
        $requested = $data['billing_mode'] ?? 'invoice';

        if ($requested !== 'deposit') {
            return 'invoice';
        }

        $isCloudServer = ! empty($data['server_id']) && Server::where('id', $data['server_id'])
            ->cloud()
            ->exists();

        return $isCloudServer ? 'deposit' : 'invoice';
    }

    private function withExtras(Request $request, array $data): array
    {
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_featured'] = $request->boolean('is_featured');

        // Jumlah hari siklus custom CUMA boleh diubah Superadmin -- dijaga
        // di sini juga (bukan cuma disembunyikan di tampilan), supaya
        // tidak bisa diakali admin/staff biasa lewat request langsung.
        if (! auth('admin')->user()->isSuperadmin()) {
            unset($data['custom_cycle_days']);
        }

        // Textarea satu fitur per baris -> array bersih (baris kosong dibuang).
        $features = collect(explode("\n", (string) $request->input('features_raw')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        $data['features'] = $features;

        return $data;
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'product_category_id' => ['required', 'exists:product_groups,id'],
            'name'                => ['required', 'string', 'max:255'],
            'slug'                => ['nullable', 'string', 'max:255', 'unique:products,slug' . ($ignoreId ? ",{$ignoreId}" : '')],
            'tagline'             => ['nullable', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'price_monthly'       => ['nullable', 'numeric', 'min:0'],
            'price_quarterly'     => ['nullable', 'numeric', 'min:0'],
            'price_semi_annually' => ['nullable', 'numeric', 'min:0'],
            'price_annually'      => ['nullable', 'numeric', 'min:0'],
            'price_custom'        => ['nullable', 'numeric', 'min:0'],
            'custom_cycle_days'   => ['nullable', 'integer', 'min:1', 'max:3650'],
            'setup_fee'           => ['nullable', 'numeric', 'min:0'],
            'domain_option'       => ['required', 'in:required,optional,none'],
            'server_id'           => ['nullable', 'exists:servers,id'],
            'panel_package'       => ['nullable', 'string', 'max:500'],
            'billing_mode'        => ['nullable', 'in:invoice,deposit'],
            'pricing_mode'        => ['nullable', 'in:manual,markup'],
            'markup_percent'      => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'price_per_vcpu_hour' => ['nullable', 'numeric', 'min:0'],
            'price_per_ram_gb_hour' => ['nullable', 'numeric', 'min:0'],
            'price_per_storage_gb_hour' => ['nullable', 'numeric', 'min:0'],
            'price_per_backup_gb_hour' => ['nullable', 'numeric', 'min:0'],
            'price_per_snapshot_gb_hour' => ['nullable', 'numeric', 'min:0'],
            'price_windows_license_per_vcpu_hour' => ['nullable', 'numeric', 'min:0'],
            'stock'               => ['nullable', 'integer', 'min:0'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);
    }

    // ── Spesifikasi & harga VPS (multi-provider) ─────────────────────────

    /** Kolom kartu harga per-jam pada produk (dipakai HourlyRateCalculator). */
    private const RATE_FIELDS = [
        'pricing_mode', 'markup_percent', 'price_per_vcpu_hour', 'price_per_ram_gb_hour',
        'price_per_storage_gb_hour', 'price_per_backup_gb_hour', 'price_per_snapshot_gb_hour',
        'price_windows_license_per_vcpu_hour',
    ];

    /**
     * Merakit spesifikasi VM produk (disimpan sebagai JSON di panel_package)
     * dari isian form (vm_*) + isian khusus provider di
     * config/vps_providers.php › product_fields. SATU-SATUNYA tempat spek
     * VPS produk dirakit (dulu dirakit di JavaScript form).
     *
     * Provider berbasis size (mis. DigitalOcean): vCPU/RAM/disk otomatis
     * mengikuti size yang dipilih, diambil dari data yang ditarik lewat
     * "Tarik Harga Modal" di halaman Server.
     *
     * @throws ValidationException
     */
    private function buildVmSpec(Request $request, Server $server): array
    {
        $spec = [
            'vcpu'           => max(1, (int) $request->input('vm_vcpu', 1)),
            'ram'            => max(512, (int) $request->input('vm_ram', 1024)),
            'disk'           => max(20, (int) $request->input('vm_disk', 20)),
            'backup_enabled' => $request->input('vm_backup') === '1',
        ];

        $errors = [];

        foreach ((array) config('vps_providers.' . $server->vpsDriver() . '.product_fields', []) as $key => $field) {
            $value = trim((string) $request->input("vm_{$key}", $field['default'] ?? ''));

            if ($value === '') {
                if ($field['required'] ?? false) {
                    $errors["vm_{$key}"] = "{$field['label']} wajib diisi untuk provider {$server->vpsLabel()}.";
                }

                continue;
            }

            if (! preg_match('/^[A-Za-z0-9._-]{1,100}$/', $value)) {
                $errors["vm_{$key}"] = "{$field['label']} hanya boleh huruf, angka, titik, strip, dan underscore.";

                continue;
            }

            $spec[$key] = $value;
        }

        if (! $errors && $server->costModel() === 'size' && isset($spec['provider_size'])) {
            $size = $server->cost_cache['sizes'][$spec['provider_size']] ?? null;

            if (! $size) {
                $errors['vm_provider_size'] = "Size \"{$spec['provider_size']}\" tidak ada di daftar {$server->vpsLabel()}. "
                    . 'Tarik harga modal di halaman Server dulu, lalu pilih size yang tersedia.';
            } else {
                $spec['vcpu'] = $size['vcpu'];
                $spec['ram'] = $size['ram'];
                $spec['disk'] = $size['disk'];
                $spec['backup_enabled'] = false;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $spec;
    }

    /** Produk di server VPS: panel_package = JSON spek hasil buildVmSpec(). */
    private function withVmSpec(Request $request, array $data): array
    {
        $server = ! empty($data['server_id']) ? Server::cloud()->find($data['server_id']) : null;

        if ($server) {
            $data['panel_package'] = json_encode($this->buildVmSpec($request, $server));
        }

        return $data;
    }

    /**
     * Cek harga produk VPS terhadap harga modal provider.
     *  - Deposit: tarif per jam yang dihasilkan WAJIB > 0. Tarif 0 berarti
     *    VPS tidak pernah ditagih sama sekali (ChargeHourlyUsage melewati
     *    layanan bertarif 0), jadi ditolak di sini.
     *  - Harga jual di bawah harga modal: tetap boleh disimpan, tapi
     *    diperingatkan (deposit: per jam; invoice: harga bulanan vs modal
     *    730 jam).
     *
     * @return string[] peringatan
     * @throws ValidationException
     */
    private function pricingWarnings(array $data): array
    {
        $server = ! empty($data['server_id']) ? Server::cloud()->find($data['server_id']) : null;

        if (! $server) {
            return [];
        }

        $spec = json_decode((string) ($data['panel_package'] ?? ''), true) ?: [];
        $modal = HourlyRateCalculator::providerCost($server, $spec);

        if (($data['billing_mode'] ?? 'invoice') === 'deposit') {
            $draft = (new Product())->forceFill(Arr::only($data, self::RATE_FIELDS));
            $sell = HourlyRateCalculator::calculate($server, $spec, $draft);

            if ($sell <= 0) {
                throw ValidationException::withMessages(['pricing_mode' => $this->zeroRateMessage($server, $data, $modal)]);
            }

            return ($modal > 0 && $sell < $modal)
                ? [sprintf('Harga jual per jam (Rp %s) di bawah harga modal provider (Rp %s) — produk ini rugi.', number_format($sell, 2, ',', '.'), number_format($modal, 2, ',', '.'))]
                : [];
        }

        $monthly = (float) ($data['price_monthly'] ?? 0);

        return ($modal > 0 && $monthly > 0 && $monthly < $modal * 730)
            ? [sprintf('Harga bulanan (Rp %s) di bawah estimasi harga modal provider sebulan (Rp %s).', number_format($monthly, 0, ',', '.'), number_format($modal * 730, 0, ',', '.'))]
            : [];
    }

    private function zeroRateMessage(Server $server, array $data, float $modal): string
    {
        if (($data['pricing_mode'] ?? 'manual') === 'markup') {
            return $modal > 0
                ? 'Tarif per jam masih 0 — isi Markup (%) lebih dari 0.'
                : 'Harga modal belum bisa dihitung — tarik harga modal di halaman Server'
                    . ($server->costFxRate() <= 0 && $server->costCurrency() !== 'IDR' ? ' dan isi kurs ke Rupiah-nya' : '')
                    . ', atau pakai mode Isi Manual.';
        }

        return 'Tarif per jam masih 0 — isi minimal harga per vCPU/RAM/Storage di kartu harga, atau pakai mode Markup dari harga modal provider.';
    }

    /**
     * Estimasi langsung untuk form Produk VPS: harga modal provider (per jam
     * & per bulan) untuk spek yang sedang diisi, plus tarif jual per jam
     * kalau mode tagihnya deposit. Pakai HourlyRateCalculator yang sama
     * dengan saat simpan/tagih, jadi angkanya selalu konsisten.
     */
    public function vpsEstimate(Request $request): JsonResponse
    {
        $server = Server::cloud()->find($request->input('server_id'));

        if (! $server) {
            return response()->json(['ok' => false, 'message' => 'Pilih server VPS dulu.']);
        }

        try {
            $spec = $this->buildVmSpec($request, $server);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()]);
        }

        $modal = HourlyRateCalculator::providerCost($server, $spec);
        $deposit = $request->input('billing_mode') === 'deposit';
        $draft = (new Product())->forceFill(Arr::only($request->all(), self::RATE_FIELDS));

        return response()->json([
            'ok'           => true,
            'ready'        => $modal > 0,
            'provider'     => $server->vpsLabel(),
            'currency'     => $server->costCurrency(),
            'fx_missing'   => $server->costCurrency() !== 'IDR' && $server->costFxRate() <= 0,
            'synced'       => (bool) $server->cost_cached_at,
            'modal_hourly' => $modal,
            'modal_monthly' => round($modal * 730),
            'sell_hourly'  => $deposit ? HourlyRateCalculator::calculate($server, $spec, $draft) : null,
        ]);
    }

    /**
     * Data per server VPS untuk form Produk: provider, model harga modal,
     * status penarikan harga modal, dan daftar size/region hasil tarik
     * (untuk saran isian provider berbasis size).
     */
    private function vpsServerMeta($servers): array
    {
        return $servers->filter->isCloud()->mapWithKeys(function (Server $server) {
            $cache = is_array($server->cost_cache) ? $server->cost_cache : [];

            $sizes = collect($cache['sizes'] ?? [])->map(fn ($size) => sprintf(
                '%d vCPU · %s GB RAM · %d GB disk — %s %s/bln',
                $size['vcpu'], rtrim(rtrim(number_format($size['ram'] / 1024, 1), '0'), '.'), $size['disk'],
                $server->costCurrency(), number_format((float) $size['monthly'], 2)
            ))->all();

            return [$server->id => [
                'driver'   => $server->vpsDriver(),
                'label'    => $server->vpsLabel(),
                'model'    => $server->costModel(),
                'currency' => $server->costCurrency(),
                'synced'   => (bool) $server->cost_cached_at,
                'fxReady'  => $server->costFxRate() > 0,
                'sizes'    => $sizes,
                'regions'  => $cache['regions'] ?? [],
            ]];
        })->all();
    }
}
