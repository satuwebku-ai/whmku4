<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Tld;
use App\Services\Cart\CartService;
use App\Services\Domain\AvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DomainSearchController extends Controller
{
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

    /**
     * Halaman cek domain publik. Pengecekan ketersediaan memakai RDAP,
     * bukan API registrar — lihat AvailabilityService untuk alasannya.
     */
    public function search(Request $request, AvailabilityService $checker): View
    {
        return view('public.catalog.domain-search', $this->searchData($request, $checker));
    }

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
                }
            }
        }

        $banners = \App\Models\PromoBanner::live()->forPage('domain_search')->orderBy('sort_order')->get();

        return compact('results', 'query', 'tldPrices', 'allTldPrices', 'selected', 'groups', 'banners');
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
    public function addToCart(Request $request, CartService $cart): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'domain_name' => ['required', 'string', 'max:255'],
            'tld_id'      => ['required', 'exists:tlds,id'],
            'years'       => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $tld = Tld::findOrFail($data['tld_id']);

        $result = $cart->addDomain($data['domain_name'], $tld, (int) ($data['years'] ?? 1));

        // Dipanggil lewat fetch() dari halaman Cek Domain supaya klien bisa
        // terus menambah beberapa domain tanpa halaman reload tiap kali —
        // form biasa (tanpa JS) tetap jalan normal lewat redirect di bawah.
        if ($request->wantsJson()) {
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'cart_count' => $cart->count(),
            ]);
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Halaman transfer domain masuk — untuk klien yang domainnya masih
     * di registrar lain dan mau dipindahkan ke sini.
     */
    public function transferForm(): View
    {
        $tlds = Tld::where('is_active', true)
            ->whereNotNull('transfer_price')
            ->where('transfer_price', '>', 0)
            ->orderBy('extension')
            ->get();

        return view('public.catalog.domain-transfer', compact('tlds'));
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
        $data = $request->validate([
            'domain_name' => ['required', 'string', 'max:255'],
            'auth_code'   => ['required', 'string', 'max:255'],
        ], [
            'auth_code.required' => 'Kode EPP/Auth Code wajib diisi — minta ke registrar lama Anda.',
        ]);

        $domainName = strtolower(trim($data['domain_name']));

        // Cari TLD yang cocok dari nama domain yang diketik klien —
        // dicocokkan dari ekstensi TERPANJANG dulu supaya ".co.id" tidak
        // salah dikenali sebagai ".id".
        $tld = Tld::where('is_active', true)
            ->get()
            ->sortByDesc(fn ($t) => strlen($t->extension))
            ->first(fn ($t) => str_ends_with($domainName, '.' . ltrim($t->extension, '.')));

        if (! $tld) {
            return back()->with('error', 'Ekstensi domain tersebut belum kami dukung untuk transfer.')->withInput();
        }

        $result = $cart->addDomain($domainName, $tld, 1, true, $data['auth_code']);

        return $result['success']
            ? redirect()->route('cart.index')->with('success', $result['message'])
            : back()->with('error', $result['message'])->withInput();
    }
}
