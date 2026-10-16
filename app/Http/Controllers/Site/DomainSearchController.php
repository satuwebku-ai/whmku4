<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\HandlesStoreHandoff;
use App\Http\Controllers\Controller;
use App\Models\CustomPremiumDomain;
use App\Models\DomainInterest;
use App\Models\Registrar;
use App\Models\Tld;
use App\Models\TldPremium;
use App\Services\Domain\DnamaService;
use App\Services\Cart\CartService;
use App\Services\Domain\AvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class DomainSearchController extends Controller
{
    use HandlesStoreHandoff;

    /**
     * Jumlah hasil yang ditampilkan dalam satu pencarian.
     *
     * Membatasi hasil (bukan membatasi pilihan) menjaga halaman tetap enak
     * dibaca sekaligus mencegah ratusan permintaan RDAP sekaligus.
     */
    private const MAX_RESULTS = 20;

    /**
     * Ekstensi yang diprioritaskan saat pengunjung belum mencentang apa pun.
     * Dipakai untuk mengurutkan, bukan untuk membatasi — sisanya tetap ikut
     * dicek sampai kuota MAX_RESULTS terpenuhi.
     */
    private const PRIORITY_EXTENSIONS = [
        '.com', '.id', '.co.id', '.net', '.my.id', '.web.id',
        '.org', '.xyz', '.online', '.site', '.info', '.biz',
    ];

    public function searchBootstrap(Request $request, AvailabilityService $checker): View
    {
        return view('public.catalog.domain-search', $this->searchData($request, $checker));
    }

    private function searchData(Request $request, AvailabilityService $checker): array
    {
        $results = null;
        $query = trim((string) $request->input('domain'));

        // Ekstensi yang ditampilkan sebagai pilihan centang: yang sengaja
        // ditandai admin lewat "Tampil di Web".
        $tldPrices = Tld::visibleInSearch()
            ->orderBy('search_order')
            ->orderBy('extension')
            ->get()
            ->keyBy('extension');

        // Koleksi TERPISAH dan LEBIH LUAS, khusus untuk mencari harga di
        // hasil pencarian — pencarian otomatis (tanpa centang) menjangkau
        // SEMUA TLD aktif, bukan cuma yang tampil di daftar checkbox, jadi
        // kalau harga ikut dibatasi ke $tldPrices, hasil yang TLD-nya tidak
        // dicentang tampil di daftar akan kehilangan harganya walau
        // sebenarnya bisa dibeli.
        $allTldPrices = Tld::where('is_active', true)->get()->keyBy('extension');

        $groups = $tldPrices->groupBy(fn ($tld) => $tld->search_group_label);

        // Tidak ada yang dicentang otomatis — pengunjung bebas memilih.
        $selected = array_values(array_intersect(
            (array) $request->input('extensions', []),
            $tldPrices->keys()->all()
        ));

        if ($query) {
            $base = $this->normalizeName($query);

            if ($base === '') {
                $results = [
                    'success' => false,
                    'message' => 'Nama domain tidak valid. Gunakan huruf, angka, dan tanda hubung.',
                    'results' => [], 'unknown' => [],
                ];
            } else {
                $candidates = $this->buildCandidates($base, $selected, $request->input('domain'));

                if (empty($candidates)) {
                    $results = ['success' => false, 'message' => 'Belum ada ekstensi domain yang dijual saat ini.', 'results' => [], 'unknown' => []];
                } else {
                    DomainInterest::recordSearch($candidates, [
                        'query' => $query,
                        'extensions' => $selected,
                    ]);

                    // TLD demo tidak punya server RDAP (memang bukan TLD
                    // sungguhan), jadi dipisahkan dan langsung dianggap
                    // tersedia — kalau ikut dicek, statusnya akan selalu
                    // "belum pasti" dan alur pemesanan tidak bisa dicoba.
                    $demoExtensions = Tld::where('is_demo', true)->pluck('extension')->all();

                    $demoDomains = [];
                    $realDomains = [];

                    foreach ($candidates as $candidate) {
                        $ext = '.' . \Illuminate\Support\Str::after($candidate, '.');

                        if (in_array($ext, $demoExtensions, true)) {
                            $demoDomains[$candidate] = true;
                        } else {
                            $realDomains[] = $candidate;
                        }
                    }

                    $results = $realDomains
                        ? $checker->check($realDomains)
                        : ['success' => true, 'message' => 'OK', 'results' => [], 'unknown' => []];

                    // Domain demo ditaruh paling atas agar mudah ditemukan.
                    $results['results'] = $demoDomains + $results['results'];
                    $this->attachPremiumDetails($results, $allTldPrices);
                }
            }
        }

        $banners = \App\Models\PromoBanner::live()->forPage('domain_search')->orderBy('sort_order')->get();

        return compact('results', 'query', 'tldPrices', 'allTldPrices', 'selected', 'groups', 'banners');
    }

    /**
     * Tandai hasil yang memang dijual sebagai premium agar tidak salah
     * ditawarkan dengan harga TLD reguler.
     *
     * Sumber premium yang dipakai:
     * - Domain premium custom yang sudah diberi harga jual oleh admin.
     * - Semua baris harga bertingkat aktif dari halaman TLD Premium.
     *   Klasifikasi dan harga keluarga .id ditentukan oleh tingkat
     *   karakter PANDI dan sell_register_price yang diatur admin; flag
     *   is_premium dari DNAMA tidak menimpa aturan tier tersebut.
     * - Ekstensi generik yang terdaftar di halaman TLD Premium. DNAMA
     *   memeriksa status per nama; harga dan pemesanannya manual.
     */
    private function attachPremiumDetails(array &$searchResults, $allTldPrices): void
    {
        $searchResults['premium'] = [];
        $searchResults['premium_check_unknown'] = [];
        $domains = array_keys($searchResults['results'] ?? []);

        if (! $domains) {
            return;
        }

        $normalizedDomains = array_map('strtolower', $domains);
        $customPremiums = CustomPremiumDomain::query()
            ->where('is_active', true)
            ->where('sell_price', '>', 0)
            ->whereIn(DB::raw('LOWER(domain_name)'), $normalizedDomains)
            ->get()
            ->keyBy(fn (CustomPremiumDomain $domain) => strtolower($domain->domain_name));

        foreach ($customPremiums as $domainName => $premium) {
            // Nama yang sudah terdaftar tetap tidak tersedia, meskipun
            // sebelumnya pernah dimasukkan ke katalog premium custom.
            if (($searchResults['results'][$domainName] ?? null) === false) {
                continue;
            }

            $price = (float) $premium->sell_price;
            $searchResults['premium'][$domainName] = [
                'type' => 'custom',
                'id' => $premium->id,
                'price' => $price,
                'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
                'verified' => true,
            ];
        }

        $registrar = Registrar::where('provider', 'dnama')->where('is_active', true)->first();

        if (! $registrar) {
            return;
        }

        $extensionsInSearch = $allTldPrices->keys()->all();
        $premiumTiers = TldPremium::query()
            ->where('registrar_id', $registrar->id)
            ->whereIn('extension', $extensionsInSearch)
            ->where('is_active', true)
            ->where('is_premium', true)
            ->where('is_generic', false)
            ->whereNotNull('max_premium_character')
            ->orderBy('max_premium_character')
            ->get()
            ->groupBy('extension');

        $genericPremiumExtensions = TldPremium::query()
            ->where('registrar_id', $registrar->id)
            ->whereIn('extension', $extensionsInSearch)
            ->where('is_active', true)
            ->where('is_premium', true)
            ->where('is_generic', true)
            ->get()
            ->keyBy('extension');

        $extensions = collect($allTldPrices->keys()->all())
            ->sortByDesc(fn ($extension) => strlen($extension))
            ->values()
            ->all();

        foreach ($domains as $domainName) {
            $domainKey = strtolower($domainName);

            // RDAP sudah mengonfirmasi domain terdaftar. Jangan ubah status
            // tersebut menjadi premium/cek ulang hanya karena pemeriksaan
            // premium registrar tidak dijalankan untuk domain yang taken.
            if (($searchResults['results'][$domainName] ?? null) === false) {
                continue;
            }

            // Domain yang sudah masuk katalog premium custom memakai harga
            // khususnya sendiri, bukan harga bertingkat keluarga .id.
            if (isset($searchResults['premium'][$domainKey])) {
                continue;
            }

            $extension = collect($extensions)->first(fn ($candidate) => str_ends_with($domainKey, $candidate));

            if (! $extension) {
                continue;
            }

            $label = substr($domainKey, 0, -strlen($extension));
            $tier = $premiumTiers->get($extension, collect())
                ->first(fn (TldPremium $row) => strlen($label) <= (int) $row->max_premium_character);

            $tld = $allTldPrices->get($extension);
            $isDnamaTld = $tld && (int) $tld->registrar_id === (int) $registrar->id;
            $supportsPerNameCheck = $genericPremiumExtensions->has($extension)
                || $premiumTiers->has($extension)
                || $isDnamaTld;
            $verified = null;
            $isVerified = false;

            // Periksa status langsung ke DNAMA untuk ekstensi generik,
            // ekstensi yang dikelola DNAMA, serta tier .id yang tidak cocok
            // dengan panjang nama. Ini menangkap premium per-nama di luar
            // daftar tier tetap tanpa menebak dari harga reguler.
            if ($supportsPerNameCheck && ($searchResults['results'][$domainName] ?? null) !== false) {
                try {
                    $verified = Cache::remember(
                        "dnama-premium-check:{$domainKey}",
                        now()->addMinutes(10),
                        fn () => (new DnamaService($registrar))->checkPremiumStatus($domainKey)
                    );
                } catch (Throwable) {
                    $verified = null;
                }
            }

            $isVerified = is_array($verified) && ($verified['success'] ?? false);

            if ($isVerified) {
                if (($verified['available'] ?? null) === false) {
                    $searchResults['results'][$domainName] = false;
                    continue;
                } elseif (($verified['available'] ?? null) === true) {
                    $searchResults['results'][$domainName] = true;
                }
            }

            // Kalau ada tier karakter yang cocok, aturan PANDI dan harga jual
            // admin menang. Flag is_premium DNAMA tidak menurunkannya ke harga
            // reguler.
            if ($tier) {
                // Jika DNAMA tidak bisa dihubungi, tier lokal tetap berlaku;
                // catatan di halaman hanya menyebut status ketersediaan.
                $price = (float) ($tier->sell_register_price ?? 0);
                $searchResults['premium'][$domainKey] = [
                    'type' => 'tier',
                    'id' => $tier->id,
                    'price' => $price,
                    'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
                    'verified' => $isVerified,
                ];

                continue;
            }

            if (! $supportsPerNameCheck) {
                continue;
            }

            // Tanpa harga bertingkat yang cocok, status premium ditentukan
            // per nama oleh DNAMA. Jika pemeriksaan gagal, tahan pesanan
            // reguler supaya domain premium tidak salah terjual murah.
            if (! $isVerified) {
                $searchResults['results'][$domainName] = null;
                $searchResults['premium_check_unknown'][$domainKey] = true;

                continue;
            }

            if ($verified['is_premium'] ?? false) {
                $searchResults['premium'][$domainKey] = [
                    'type' => 'generic',
                    'id' => $genericPremiumExtensions->get($extension)?->id,
                    'price' => 0,
                    'price_formatted' => null,
                    'verified' => true,
                ];
            }
        }

        // Sinkronkan daftar "belum pasti" dengan pemeriksaan RDAP/DNAMA terbaru.
        $searchResults['unknown'] = array_keys(array_filter(
            $searchResults['results'],
            fn ($available) => $available === null
        ));
    }

    /**
     * Susun daftar domain yang akan dicek.
     *
     * Kalau pengunjung mencentang ekstensi, itu yang dipakai. Kalau tidak,
     * sistem memilihkan sendiri dari SELURUH TLD aktif di database — bukan
     * hanya yang tampil sebagai checkbox — supaya pencarian tetap luas
     * meski pengunjung tidak menyentuh satu pun centang.
     *
     * @param  string[]  $selected
     * @return string[]
     */
    private function buildCandidates(string $base, array $selected, ?string $rawQuery): array
    {
        if ($selected) {
            return array_map(
                fn ($ext) => $base . $ext,
                array_slice($selected, 0, self::MAX_RESULTS)
            );
        }

        // Semua TLD yang dijual, bukan sekadar yang tampil di halaman.
        $all = Tld::active()->pluck('extension')->all();

        if (empty($all)) {
            return [];
        }

        $ordered = [];

        // 1. Ekstensi yang diketik pengunjung didahulukan — kalau mengetik
        //    "saya.com", hasil .com wajib ada di daftar.
        if ($rawQuery && str_contains($rawQuery, '.')) {
            $typed = '.' . strtolower(\Illuminate\Support\Str::after(trim($rawQuery), '.'));

            if (in_array($typed, $all, true)) {
                $ordered[] = $typed;
            }
        }

        // 2. Ekstensi populer.
        foreach (self::PRIORITY_EXTENSIONS as $ext) {
            if (in_array($ext, $all, true) && ! in_array($ext, $ordered, true)) {
                $ordered[] = $ext;
            }
        }

        // 3. Sisanya, untuk mengisi kuota.
        foreach ($all as $ext) {
            if (! in_array($ext, $ordered, true)) {
                $ordered[] = $ext;
            }
        }

        return array_map(
            fn ($ext) => $base . $ext,
            array_slice($ordered, 0, self::MAX_RESULTS)
        );
    }

    /**
     * Bersihkan input jadi nama domain yang sah (tanpa ekstensi).
     *
     * Pengunjung sering mengetik "saya.com", "www.saya", atau menyertakan
     * spasi — semuanya diringkas jadi "saya" supaya bisa digabung dengan
     * ekstensi pilihan. Karakter di luar huruf/angka/tanda hubung dibuang
     * karena akan ditolak registry.
     */
    private function normalizeName(string $query): string
    {
        $name = strtolower(trim($query));
        $name = preg_replace('/^https?:\/\//', '', $name);
        $name = preg_replace('/^www\./', '', $name);
        $name = explode('/', $name)[0];

        // Buang ekstensi kalau pengunjung mengetiknya sekalian.
        $name = explode('.', $name)[0];

        // Hanya huruf, angka, dan tanda hubung; tidak boleh diawali/diakhiri
        // tanda hubung.
        $name = preg_replace('/[^a-z0-9\-]/', '', $name);

        return trim($name, '-');
    }

    /**
     * Tambahkan domain yang tersedia ke keranjang.
     */
    public function addToCart(Request $request, CartService $cart): RedirectResponse
    {
        $data = $this->validateHandoff($request, [
            'domain_name' => ['required', 'string', 'max:255'],
            'tld_id'      => ['required', 'exists:tlds,id'],
            'years'       => ['nullable', 'integer', 'min:1', 'max:10'],
        ], [], fn () => $this->searchBackUrl($request));

        $tld = Tld::findOrFail($data['tld_id']);

        $result = $cart->addDomain($data['domain_name'], $tld, (int) ($data['years'] ?? 1));

        // Dari situs publik berhasil -> lanjut ke keranjang (alur "pilih -> keranjang");
        // gagal -> kembali ke hasil pencarian beserta pesannya.
        if ($this->fromPublicSite($request)) {
            if (! $result['success']) {
                return $this->handoffFailure($request, $result['message'], fn () => $this->searchBackUrl($request));
            }

            return redirect()->route('cart.index')->with('success', $result['message']);
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /** Halaman cek domain publik (dengan kata kunci yang sama) tempat form dikirim. */
    private function searchBackUrl(Request $request): string
    {
        return route('domain.search', array_filter(['domain' => trim((string) $request->input('domain_name'))]));
    }

    public function transferFormBootstrap(): View
    {
        $tlds = Tld::where('is_active', true)
            ->whereNotNull('transfer_price')
            ->where('transfer_price', '>', 0)
            ->orderBy('extension')
            ->get();

        return view('public.catalog.domain-transfer', compact('tlds'));
    }

    public function submitTransfer(Request $request, CartService $cart): RedirectResponse
    {
        $data = $this->validateHandoff($request, [
            'domain_name' => ['required', 'string', 'max:255'],
            'auth_code'   => ['required', 'string', 'max:255'],
        ], [
            'auth_code.required' => 'Kode EPP/Auth Code wajib diisi — minta ke registrar lama Anda.',
        ], fn () => route('domains.transfer'));

        $domainName = strtolower(trim($data['domain_name']));

        // Cari TLD yang cocok dari nama domain yang diketik klien —
        // dicocokkan dari ekstensi TERPANJANG dulu supaya ".co.id" tidak
        // salah dikenali sebagai ".id".
        $tld = Tld::where('is_active', true)
            ->get()
            ->sortByDesc(fn ($t) => strlen($t->extension))
            ->first(fn ($t) => str_ends_with($domainName, '.' . ltrim($t->extension, '.')));

        if (! $tld) {
            return $this->handoffFailure($request, 'Ekstensi domain tersebut belum kami dukung untuk transfer.', fn () => route('domains.transfer'));
        }

        $result = $cart->addDomain($domainName, $tld, 1, true, $data['auth_code']);

        return $result['success']
            ? redirect()->route('cart.index')->with('success', $result['message'])
            : $this->handoffFailure($request, $result['message'], fn () => route('domains.transfer'));
    }
}
