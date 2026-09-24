<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Registrar;
use App\Models\Tld;
use App\Services\Domain\DomainRegistrarFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrarController extends Controller
{
    public function index(): View
    {
        $registrars = Registrar::withCount(['tlds', 'domains'])->latest()->paginate(10);

        // Cuma dicoba untuk registrar aktif yang benar-benar mendukungnya
        // (sekarang: Liqu.id) — supaya membuka halaman ini tidak jadi
        // lambat gara-gara menunggu API tiap kali dimuat untuk registrar
        // yang tidak punya fitur ini.
        $balances = [];
        // Dukungan fitur lanjutan (sinkron TLD, riwayat transaksi,
        // diagnosa) dicek lewat method_exists() -- BUKAN nama provider
        // yang di-hardcode di view -- supaya provider baru manapun
        // (Dnama, dst) otomatis dapat tombol ini asal service-nya
        // benar-benar mengimplementasikan method yang dibutuhkan.
        $supportsSync = [];
        // Tombol "Tarik Customer" cuma muncul untuk provider yang punya
        // endpoint daftar customer (mis. Liqu.id) -- provider tanpa
        // endpoint ini (mis. DNAMA, cuma bisa cari per username) tidak
        // dapat tombol yang ujung-ujungnya gagal kalau diklik.
        $supportsCustomers = [];

        foreach ($registrars as $registrar) {
            if (! $registrar->is_active) {
                continue;
            }

            $service = \App\Services\Domain\DomainRegistrarFactory::make($registrar);

            $supportsSync[$registrar->id] = method_exists($service, 'listTlds');
            $supportsCustomers[$registrar->id] = method_exists($service, 'listCustomers');

            if (method_exists($service, 'getAccountBalance')) {
                try {
                    $result = $service->getAccountBalance();
                    $balances[$registrar->id] = $result['success'] ? $result : null;
                } catch (\Throwable $e) {
                    $balances[$registrar->id] = null;
                }
            }
        }

        return view('admin.registrars.index', compact('registrars', 'balances', 'supportsSync', 'supportsCustomers'));
    }

    public function indexBootstrap(): View
    {
        $registrars = Registrar::withCount(['tlds', 'domains'])->latest()->paginate(10);

        $balances = [];
        $supportsSync = [];
        $supportsCustomers = [];

        foreach ($registrars as $registrar) {
            if (! $registrar->is_active) {
                continue;
            }

            $service = \App\Services\Domain\DomainRegistrarFactory::make($registrar);

            $supportsSync[$registrar->id] = method_exists($service, 'listTlds');
            $supportsCustomers[$registrar->id] = method_exists($service, 'listCustomers');

            if (method_exists($service, 'getAccountBalance')) {
                try {
                    $result = $service->getAccountBalance();
                    $balances[$registrar->id] = $result['success'] ? $result : null;
                } catch (\Throwable $e) {
                    $balances[$registrar->id] = null;
                }
            }
        }

        return view('admin.registrars.index', compact('registrars', 'balances', 'supportsSync', 'supportsCustomers'));
    }

    public function create(): View
    {
        return view('admin.registrars.form', ['registrar' => new Registrar()]);
    }

    public function createBootstrap(): View
    {
        return view('admin.registrars.form', ['registrar' => new Registrar()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sandbox'] = $request->boolean('sandbox', true);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default');

        if ($data['is_default']) {
            Registrar::query()->update(['is_default' => false]);
        }

        Registrar::create($data);

        return redirect()->route('admin.registrars.index')->with('success', 'Registrar berhasil ditambahkan.');
    }

    public function edit(Registrar $registrar): View
    {
        return view('admin.registrars.form', compact('registrar'));
    }

    public function editBootstrap(Registrar $registrar): View
    {
        return view('admin.registrars.form', compact('registrar'));
    }

    public function update(Request $request, Registrar $registrar): RedirectResponse
    {
        $data = $this->validated($request, updating: true);
        $data['sandbox'] = $request->boolean('sandbox');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_default'] = $request->boolean('is_default');

        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }

        if ($data['is_default']) {
            Registrar::query()->where('id', '!=', $registrar->id)->update(['is_default' => false]);
        }

        $registrar->update($data);

        return redirect()->route('admin.registrars.index')->with('success', 'Registrar berhasil diperbarui.');
    }

    public function destroy(Registrar $registrar): RedirectResponse
    {
        if ($registrar->domains()->exists() || $registrar->tlds()->exists()) {
            return back()->with('error', 'Registrar tidak bisa dihapus karena masih dipakai oleh TLD/domain.');
        }

        $registrar->delete();

        return redirect()->route('admin.registrars.index')->with('success', 'Registrar berhasil dihapus.');
    }

    /**
     * Halaman diagnosa — memanggil beberapa endpoint GET (cuma baca,
     * TIDAK mengubah apa pun) untuk memastikan hal-hal yang tidak bisa
     * disimpulkan dari satu endpoint saja: mata uang akun, saldo, dan
     * format angka harga yang sebenarnya dikembalikan API.
     */
    public function diagnostics(Request $request, Registrar $registrar): View
    {
        return view($this->diagnosticsView($registrar), $this->diagnosticsData($registrar, $request));
    }

    public function diagnosticsBootstrap(Request $request, Registrar $registrar): View
    {
        return view($this->diagnosticsView($registrar), $this->diagnosticsData($registrar, $request));
    }

    /**
     * Tiap provider punya bentuk data mentah & istilah yang beda banget
     * (mis. Liqu.id pakai USD & customer per-domain, DNAMA pakai IDR
     * tanpa konsep customer) -- daripada satu file diagnostics.blade.php
     * penuh percabangan @if($registrar->provider === ...) untuk semua
     * provider, tiap provider yang sudah "matang" dapat file tampilan
     * sendiri (diagnostics-{provider}.blade.php). Provider yang belum
     * dibuatkan (mis. resellbiz, namecheap) jatuh balik ke file generic.
     */
    private function diagnosticsView(Registrar $registrar): string
    {
        $specific = "admin.registrars.diagnostics-{$registrar->provider}";

        return \Illuminate\Support\Facades\View::exists($specific) ? $specific : 'admin.registrars.diagnostics';
    }

    private function diagnosticsData(Registrar $registrar, ?Request $request = null): array
    {
        $service = DomainRegistrarFactory::make($registrar);
        $details = null;
        $balance = null;
        $priceSample = null;
        $priceStats = null;
        $apiErrors = [];

        if (method_exists($service, 'getAccountDetails')) {
            try {
                $result = $service->getAccountDetails();
                $details = $result['success'] ? $result : null;

                if (! $result['success']) {
                    $apiErrors[] = 'Detail akun: ' . $result['message'];
                }
            } catch (\Throwable $e) {
                $apiErrors[] = 'Detail akun: ' . $e->getMessage();
            }
        }

        if (method_exists($service, 'getAccountBalance')) {
            try {
                $result = $service->getAccountBalance();
                $balance = $result['success'] ? $result : null;

                if (! $result['success']) {
                    $apiErrors[] = 'Saldo: ' . $result['message'];
                }
            } catch (\Throwable $e) {
                $apiErrors[] = 'Saldo: ' . $e->getMessage();
            }
        }

        if (method_exists($service, 'getAccountPricesRaw')) {
            try {
                $result = $service->getAccountPricesRaw();
                $raw = $result['raw'];
                $priceSample = is_array($raw) ? array_slice($raw, 0, 3, true) : $raw;

                // Statistik jumlah -- menjawab pertanyaan "kenapa TLD
                // yang tersinkron cuma sedikit" dengan ANGKA, bukan
                // tebakan: berapa total baris dari API, berapa yang
                // dilewati karena premium, dan berapa ekstensi unik
                // yang benar-benar bisa disinkron.
                if (is_array($raw)) {
                    $reguler = array_filter($raw, fn ($r) => empty($r['is_premium']));

                    $priceStats = [
                        'total' => count($raw),
                        'premium' => count($raw) - count($reguler),
                        'unique' => count(array_unique(array_column($reguler, 'tld'))),
                    ];
                }
            } catch (\Throwable $e) {
                $apiErrors[] = 'Harga: ' . $e->getMessage();
            }
        }

        $customers = [];

        if (method_exists($service, 'listCustomers')) {
            try {
                $result = $service->listCustomers();
                $customers = $result['success'] ? $result['customers'] : [];

                if (! $result['success']) {
                    $apiErrors[] = 'Daftar customer: ' . $result['message'];
                }
            } catch (\Throwable $e) {
                $apiErrors[] = 'Daftar customer: ' . $e->getMessage();
            }
        }

        // Label provider untuk judul -- supaya halaman Diagnosa tidak
        // lagi menulis "Liqu.id" untuk registrar apa pun.
        $providerLabel = ['namecheap' => 'Namecheap', 'liquid' => 'Liqu.id', 'resellbiz' => 'ResellBiz', 'dnama' => 'DNAMA'][$registrar->provider] ?? ucfirst($registrar->provider);

        // Bagian "Customer Terbaru" disembunyikan sepenuhnya kalau
        // provider-nya memang tidak punya endpoint daftar customer
        // (mis. Dnama) -- lebih jujur daripada menampilkan judul &
        // penjelasan untuk fitur yang tidak ada.
        $supportsCustomers = method_exists($service, 'listCustomers');

        // Pencarian customer per username -- dipakai provider yang punya
        // endpoint "cari customer" tapi TIDAK punya endpoint "daftar
        // semua customer" (DNAMA begitu: GET /customers/{username} ada,
        // tapi tidak ada GET /customers). Jadi disediakan kotak cari,
        // bukan daftar.
        $customerLookup = null;
        $customerQuery = trim((string) ($request?->input('customer') ?? ''));

        if ($customerQuery !== '' && method_exists($service, 'getCustomer')) {
            try {
                $result = $service->getCustomer($customerQuery);

                $customerLookup = [
                    'query' => $customerQuery,
                    'found' => $result['success'],
                    'message' => $result['message'] ?? null,
                    'data' => $result['raw']['data'] ?? null,
                ];
            } catch (\Throwable $e) {
                $customerLookup = [
                    'query' => $customerQuery,
                    'found' => false,
                    'message' => $e->getMessage(),
                    'data' => null,
                ];
            }
        }

        $supportsCustomerLookup = method_exists($service, 'getCustomer');

        return compact(
            'registrar', 'details', 'balance', 'priceSample', 'priceStats',
            'customerLookup', 'supportsCustomerLookup',
            'apiErrors', 'customers', 'providerLabel', 'supportsCustomers'
        );
    }

    /**
     * Bukan fitur untuk pengguna akhir — cuma alat bantu sementara untuk
     * melihat persis apa yang dikembalikan Liqu.id untuk saldo akun,
     * supaya kalau pemetaan field-nya meleset, bisa diperbaiki berdasarkan
     * data sungguhan, bukan tebakan.
     */
    public function debugBalance(Registrar $registrar)
    {
        $service = DomainRegistrarFactory::make($registrar);

        if (! method_exists($service, 'getAccountBalance')) {
            return response()->json(['error' => 'Registrar ini tidak mendukung getAccountBalance().']);
        }

        return response()->json($service->getAccountBalance(), 200, [], JSON_PRETTY_PRINT);
    }

    public function testConnection(Registrar $registrar): RedirectResponse
    {
        $result = DomainRegistrarFactory::make($registrar)->testConnection();

        $registrar->update([
            'last_checked_at' => now(),
            'last_check_status' => $result['success'] ? 'ok' : $result['message'],
        ]);

        $label = ['namecheap' => 'Namecheap', 'liquid' => 'Liqu.id', 'resellbiz' => 'ResellBiz', 'dnama' => 'DNAMA'][$registrar->provider] ?? $registrar->provider;

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? "Koneksi ke {$label} berhasil." : 'Koneksi gagal: ' . $result['message']
        );
    }

    /**
     * Impor daftar TLD dari registrar ke tabel TLD Pricing.
     *
     * Harga jual TIDAK ditimpa kalau TLD-nya sudah ada — supaya markup
     * yang sudah kamu atur tidak hilang saat sinkronisasi ulang.
     */
    public function transactions(Request $request, Registrar $registrar): View|RedirectResponse
    {
        return $this->transactionsView($request, $registrar, 'admin.registrars.transactions');
    }

    public function transactionsBootstrap(Request $request, Registrar $registrar): View|RedirectResponse
    {
        return $this->transactionsView($request, $registrar, 'admin.registrars.transactions');
    }

    private function transactionsView(Request $request, Registrar $registrar, string $view): View|RedirectResponse
    {
        $service = DomainRegistrarFactory::make($registrar);

        if (! method_exists($service, 'getAccountTransactions')) {
            return back()->with('error', 'Registrar ini belum mendukung riwayat transaksi lewat sistem.');
        }

        $page = max(1, (int) $request->input('page', 1));
        $result = $service->getAccountTransactions(25, $page);

        return view($view, [
            'registrar' => $registrar,
            'transactions' => $result['transactions'],
            'warning' => $result['success'] ? null : $result['message'],
            'page' => $page,
        ]);
    }

    /**
     * Tarik daftar Customer dari akun registrar (mis. Liqu.id) dan
     * unduh sebagai CSV. Ini murni baca (GET /customers) -- tidak
     * mengubah/menghapus apa pun di sisi registrar maupun database
     * lokal. Berguna untuk melihat/menyalin data customer yang sudah
     * pernah dibuat di registrar tanpa harus login ke dashboard mereka.
     */
    public function exportCustomers(Registrar $registrar): \Symfony\Component\HttpFoundation\StreamedResponse|RedirectResponse
    {
        $service = DomainRegistrarFactory::make($registrar);

        if (! method_exists($service, 'listCustomers')) {
            return back()->with('error', 'Registrar ini tidak punya endpoint daftar customer.');
        }

        $result = $service->listCustomers(500);

        if (! $result['success']) {
            return back()->with('error', 'Gagal menarik data customer: ' . $result['message']);
        }

        if (empty($result['customers'])) {
            return back()->with('error', 'Tidak ada data customer yang bisa diambil dari registrar ini.');
        }

        $filename = 'customers_' . $registrar->provider . '_' . $registrar->id . '_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['customer_id', 'nama', 'email', 'perusahaan']);

            foreach ($result['customers'] as $c) {
                fputcsv($out, [
                    $c['id'] ?? '',
                    $c['name'] ?? '',
                    $c['email'] ?? '',
                    $c['company'] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Tarik daftar Customer dari registrar dan TULIS ke tabel `clients`
     * lokal -- ini yang dipakai untuk skenario "data hilang", beda dari
     * exportCustomers() di atas yang cuma mengunduh CSV tanpa menyentuh
     * database.
     *
     * Pencocokan pakai EMAIL (kolom itu unique di tabel clients):
     *   - Email sudah ada di database  -> DILEWATI, tidak ditimpa. Data
     *     lokal (yang mungkin sudah diedit admin: alamat, saldo, dst)
     *     tidak boleh rusak gara-gara ditimpa versi lama dari registrar.
     *   - Email belum ada               -> dibuat Client baru.
     *
     * Password SENGAJA dikosongkan (null): Liqu.id tidak pernah mengirim
     * password asli client (memang tidak bisa, itu tersimpan ter-hash
     * di sisi mereka). Client yang datanya baru dipulihkan lewat cara
     * ini harus pakai "Lupa Password" untuk login pertama kali.
     */
    public function importCustomers(Registrar $registrar): RedirectResponse
    {
        $service = DomainRegistrarFactory::make($registrar);

        if (! method_exists($service, 'listCustomers')) {
            return back()->with('error', 'Registrar ini tidak punya endpoint daftar customer.');
        }

        $result = $service->listCustomers(500);

        if (! $result['success']) {
            return back()->with('error', 'Gagal menarik data customer: ' . $result['message']);
        }

        $created = 0;
        $skipped = 0;
        $invalid = 0;

        foreach ($result['customers'] as $c) {
            $email = trim((string) ($c['email'] ?? ''));

            if ($email === '') {
                $invalid++;
                continue;
            }

            if (Client::where('email', $email)->exists()) {
                $skipped++;
                continue;
            }

            Client::create([
                'name' => $c['name'] ?: $email,
                'email' => $email,
                'company' => $c['company'] ?? null,
                'password' => null,
                'status' => 'active',
                'internal_notes' => "Dipulihkan otomatis dari {$registrar->name} (customer_id: " . ($c['id'] ?? '-') . ') pada ' . now()->format('Y-m-d H:i'),
            ]);

            $created++;
        }

        $message = "Impor selesai: {$created} klien baru dibuat, {$skipped} dilewati (email sudah ada di database).";

        if ($invalid > 0) {
            $message .= " {$invalid} baris dilewati karena tidak ada email.";
        }

        return back()->with($created > 0 ? 'success' : 'error', $message);
    }

    public function syncTlds(Registrar $registrar): RedirectResponse
    {
        $service = DomainRegistrarFactory::make($registrar);

        if (! method_exists($service, 'listTlds')) {
            return back()->with('error', 'Provider ini belum mendukung sinkronisasi TLD otomatis.');
        }

        $result = $service->listTlds();

        if (! $result['success']) {
            return back()->with('error', 'Gagal mengambil daftar TLD: ' . $result['message']);
        }

        // Endpoint daftar TLD tidak menyertakan harga, jadi harga modal
        // diambil terpisah. Kalau gagal, sinkronisasi tetap jalan — TLD
        // tetap masuk, harganya saja yang perlu diisi manual.
        $prices = [];
        $priceMessage = '';

        if (method_exists($service, 'listPrices')) {
            $priceResult = $service->listPrices();

            if ($priceResult['success']) {
                $prices = $priceResult['prices'];
            } else {
                $priceMessage = ' Harga modal TIDAK terambil: ' . $priceResult['message'];
            }
        } else {
            $priceMessage = ' Provider ini belum mendukung pengambilan harga otomatis.';
        }

        $created = 0;
        $updated = 0;
        $priced  = 0;

        foreach ($result['tlds'] as $row) {
            // Dicocokkan per (extension, registrar) -- BUKAN per extension
            // saja. Sebelumnya satu ekstensi cuma bisa "dimiliki" SATU
            // registrar; sekarang ".com" milik Liqu.id dan ".com" milik
            // DNAMA memang SENGAJA jadi dua baris terpisah, supaya admin
            // bisa pilih sendiri mana yang mau dijual/diaktifkan lewat
            // halaman TLD Pricing (yang sekarang mengharuskan pilih
            // registrar dulu -- lihat pricing()/updatePricing() di bawah).
            $existing = Tld::where('extension', $row['extension'])
                ->where('registrar_id', $registrar->id)
                ->first();

            // Baris "manual" (belum ditautkan registrar mana pun) tetap
            // di-claim seperti sebelumnya kalau ada -- supaya TLD yang
            // dulu ditambah manual otomatis kepakai data registrar begitu
            // registrarnya baru ditambahkan, alih-alih dobel percuma.
            if (! $existing) {
                $existing = Tld::where('extension', $row['extension'])
                    ->whereNull('registrar_id')
                    ->first();
            }

            $price = $prices[$row['extension']] ?? null;

            $costRegister = $price['register'] ?? ($row['price'] !== null ? (float) $row['price'] : 0);
            $costRenew    = $price['renew'] ?? $costRegister;
            $costTransfer = $price['transfer'] ?? $costRegister;

            $costFields = [
                'cost_register'  => $costRegister ?: 0,
                'cost_renew'     => $costRenew ?: 0,
                'cost_transfer'  => $costTransfer ?: 0,
                'cost_currency'  => $price['currency'] ?? 'IDR',
                // Hanya dicap tersinkron kalau harganya benar-benar didapat.
                // Sebelumnya selalu diisi, sehingga TLD berharga 0 terlihat
                // seolah sudah tersinkron padahal harganya gagal diambil.
                'cost_synced_at' => $costRegister > 0 ? now() : null,
            ];

            // Harga per-tahun (2-10 tahun) -- cuma diisi kalau provider-nya
            // sungguhan menyediakan (DNAMA iya, lewat field pricings per
            // durasi; Liqu.id/Namecheap belum). TIDAK menimpa year_prices
            // JUAL yang sudah diisi manual oleh admin -- ini cuma acuan
            // harga MODAL per tahun, dipakai di halaman TLD Pricing
            // sebagai referensi saat admin menetapkan harga jualnya.
            $costFields['cost_year_prices'] = $price['year_prices'] ?? null;
            $costFields['cost_year_renew_prices'] = $price['year_renew_prices'] ?? null;

            if ($existing) {
                $update = $costFields;
                $update['registrar_id'] = $registrar->id;

                $existing->update($update);
                $updated++;

                if ($costRegister > 0) {
                    $priced++;
                }

                continue;
            }

            if ($costRegister > 0) {
                $priced++;
            }

            Tld::create(array_merge([
                'extension' => $row['extension'],
                'registrar_id' => $registrar->id,
                // Harga JUAL sengaja dibiarkan 0 — diisi lewat Markup Massal
                // atau manual, supaya tidak ada TLD terjual seharga modal.
                'register_price' => 0,
                'renew_price' => 0,
                'transfer_price' => 0,
                'min_years' => $row['min_years'] ?? 1,
                'max_years' => $row['max_years'] ?? 10,
                // Dinonaktifkan dulu — supaya kamu sempat menetapkan harga
                // jual sebelum TLD-nya muncul di pencarian domain.
                'is_active' => false,
            ], $costFields));

            $created++;
        }

        if ($created === 0 && $updated === 0) {
            return back()->with('error', 'Tidak ada TLD yang bisa diimpor dari registrar ini.');
        }

        $message = "Sinkronisasi selesai — {$created} TLD baru";
        $message .= $updated > 0 ? ", {$updated} diperbarui." : '.';
        $message .= " Harga modal terisi untuk {$priced} TLD.";
        $message .= $priceMessage;

        if ($priced > 0) {
            $message .= ' Selanjutnya buka TLD Pricing → pilih registrar "' . $registrar->name . '" untuk menetapkan harga jual, lalu aktifkan TLD yang ingin dijual.';
        }

        return back()->with($priceMessage ? 'error' : 'success', $message);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'provider'     => ['required', 'in:namecheap,resellbiz,liquid,dnama'],
            // API URL opsional: kalau kosong, service memakai default
            // (Namecheap, Liqu.id & DNAMA sudah punya URL bawaan sandbox/produksi).
            'api_url'      => ['nullable', 'url', 'max:255'],
            // DNAMA cuma pakai satu kredensial (API Key lewat header
            // X-API-Key) -- tidak ada konsep "API User"/Reseller ID
            // terpisah seperti provider lain, jadi field ini tidak wajib
            // khusus untuk provider ini.
            'api_username' => [$request->input('provider') === 'dnama' ? 'nullable' : 'required', 'string', 'max:100'],
            'api_key'      => [$updating ? 'nullable' : 'required', 'string'],
            'username'     => ['nullable', 'string', 'max:100'],
            'client_ip'    => ['nullable', 'ip'],
            'default_ns1'  => ['nullable', 'string', 'max:255'],
            'default_ns2'  => ['nullable', 'string', 'max:255'],
            'documents_email' => ['nullable', 'email', 'max:255'],
            'sandbox'      => ['nullable', 'boolean'],
            'is_active'    => ['nullable', 'boolean'],
            'is_default'   => ['nullable', 'boolean'],
        ]);
    }
}