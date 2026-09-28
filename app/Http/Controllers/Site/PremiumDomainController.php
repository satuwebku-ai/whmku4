<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\NavMenu;
use App\Models\Registrar;
use App\Models\TldPremium;
use App\Services\Domain\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PremiumDomainController extends Controller
{
    /**
     * Gerbang fitur -- halaman ini SENGAJA cuma bisa diakses publik
     * kalau sedang terdaftar sebagai Menu Utama atau Submenu yang aktif
     * (lihat NavMenu::isRouteRegisteredAndActive()). Tidak ada saklar
     * Aktif/Nonaktif terpisah lagi di Pengaturan -- satu-satunya sumber
     * kebenaran soal "halaman ini boleh diakses publik atau tidak" ya
     * status pendaftarannya di admin/nav-menus / admin/nav-submenus.
     */
    private function ensureActive(): void
    {
        abort_unless(NavMenu::isRouteRegisteredAndActive('domain-premium.index'), 404);
    }

    // Daftar keluarga .id & ekstensi generik (TldPremium::ID_FAMILY /
    // ::GENERIC_EXTENSIONS, dipakai langsung -- lihat method di bawah)
    // dipakai bersama dengan halaman admin "Domain Premium"
    // (TldController::premiumPricing() dkk) supaya tidak dobel.

    public function index(): View
    {
        $this->ensureActive();

        return view('public.catalog.domain-premium', $this->data());
    }

    public function indexBootstrap(): View
    {
        $this->ensureActive();

        return view('public.catalog.domain-premium', $this->data());
    }

    private function data(): array
    {
        $idFamily = $this->idFamilyPricing();
        $banners = \App\Models\PromoBanner::live()->forPage('domain_premium')->orderBy('sort_order')->get();

        return compact('idFamily', 'banners');
    }

    /**
     * Ambil harga premium keluarga .id dari tabel tld_premiums --
     * BUKAN lagi live dari API DNAMA. Harga yang tampil di sini adalah
     * harga JUAL (sell_*) yang admin isi manual di halaman admin
     * "Domain Premium" (lihat TldController::premiumPricing() &
     * syncPremiumPricing()), supaya pengunjung publik melihat harga
     * yang benar-benar kita tetapkan, bukan harga modal/sarat DNAMA.
     *
     * Kalau admin belum sempat mengisi harga jual untuk suatu tingkat,
     * baris itu jatuh balik ke harga modal (cost_*) apa adanya --
     * lebih baik tetap tampil (walau belum ada margin) daripada
     * mendadak hilang dari daftar dan terlihat seperti tidak dijual.
     */
    private function idFamilyPricing(): array
    {
        $registrar = Registrar::where('provider', 'dnama')->where('is_active', true)->first();

        if (! $registrar) {
            return ['rows' => [], 'error' => null];
        }

        $rows = TldPremium::where('registrar_id', $registrar->id)
            ->where('is_generic', false)
            ->where('is_active', true)
            ->get();

        return ['rows' => $this->groupIdFamilyRows($rows), 'error' => null];
    }

    /**
     * Kelompokkan baris tld_premiums jadi {ekstensi => [reguler, premium...]}.
     */
    private function groupIdFamilyRows($rows): array
    {
        $wanted = TldPremium::ID_FAMILY;
        $out = [];

        foreach ($rows as $row) {
            $ext = $row->extension;

            if (! $ext || ! in_array($ext, $wanted, true)) {
                continue;
            }

            // Harga jual register kosong = belum dijual: tidak ditampilkan
            // (sebelumnya jatuh ke harga modal, jadi terjual tanpa margin).
            if (! ((float) $row->sell_register_price > 0)) {
                continue;
            }

            $out[$ext][] = [
                'id' => $row->id,
                'label' => $row->label,
                'is_premium' => $row->is_premium,
                'max_premium_character' => $row->max_premium_character,
                'register' => $row->sell_register_price,
                'renew' => $row->sell_renew_price ?? $row->cost_renew,
                'transfer' => $row->sell_transfer_price ?? $row->cost_transfer,
                'currency' => $row->cost_currency ?? 'IDR',
            ];
        }

        // Urutkan sesuai urutan ID_FAMILY, dan di dalam tiap ekstensi:
        // reguler dulu, lalu premium dari jumlah karakter terkecil.
        $ordered = [];

        foreach ($wanted as $ext) {
            if (empty($out[$ext])) {
                continue;
            }

            $variants = $out[$ext];
            usort($variants, function ($a, $b) {
                if ($a['is_premium'] !== $b['is_premium']) {
                    return $a['is_premium'] <=> $b['is_premium'];
                }

                return ($a['max_premium_character'] ?? 99) <=> ($b['max_premium_character'] ?? 99);
            });

            $ordered[$ext] = $variants;
        }

        return $ordered;
    }

    /**
     * Cek satu nama domain KELUARGA .id: nama (label) + ekstensi dipilih
     * terpisah supaya kita bisa langsung menghitung tingkatan harga yang
     * tepat berdasarkan JUMLAH KARAKTER label (lihat resolvePremiumTier)
     * -- bukan cuma menampilkan tabel harga statis seperti sebelumnya.
     * Ketersediaan sungguhan dicek lewat RDAP (AvailabilityService),
     * sama seperti halaman Cek Domain biasa, supaya klien tidak bisa
     * "memesan" nama yang sebenarnya sudah terdaftar.
     */
    public function check(Request $request, AvailabilityService $availability): JsonResponse
    {
        $this->ensureActive();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:63'],
            'extension' => ['required', 'string', Rule::in(TldPremium::ID_FAMILY)],
        ]);

        $label = strtolower(trim($data['label']));
        $label = preg_replace('#^https?://#', '', $label);
        $label = preg_replace('#^www\.#', '', $label);
        $ext = $data['extension'];
        $extNoDot = ltrim($ext, '.');
        // Kalau klien menyalin nama lengkap dengan ekstensinya sendiri,
        // buang ekstensi itu supaya tidak dobel ("toko.id" + ".id").
        $label = preg_replace('#\.' . preg_quote($extNoDot, '#') . '$#i', '', $label);

        if ($label === '' || ! preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $label)) {
            return response()->json(['success' => false, 'message' => 'Nama domain tidak valid.']);
        }

        $domainName = "{$label}{$ext}";

        if (Domain::whereRaw('LOWER(domain_name) = ?', [strtolower($domainName)])->whereIn('status', ['pending', 'active'])->exists()) {
            return response()->json(['success' => true, 'domain' => $domainName, 'available' => false, 'message' => "{$domainName} sudah terdaftar/dipesan lebih dulu."]);
        }

        $registrar = Registrar::where('provider', 'dnama')->where('is_active', true)->first();

        if (! $registrar) {
            return response()->json(['success' => false, 'message' => 'Layanan pengecekan sedang tidak tersedia.']);
        }

        $result = $availability->check([$domainName]);
        $available = $result['results'][$domainName] ?? null;

        if ($available === false) {
            return response()->json(['success' => true, 'domain' => $domainName, 'available' => false, 'message' => "{$domainName} sudah terdaftar."]);
        }

        $premium = $this->resolvePremiumTier($registrar, $ext, strlen($label));

        if (! $premium) {
            // Baris reguler keluarga .id sengaja tidak disimpan di
            // tld_premiums, jadi nama yang lebih panjang dari SEMUA tingkat
            // premium ekstensi ini bukan domain premium -- arahkan ke Cek
            // Domain biasa, jangan bilang "harga belum tersedia".
            $maxTier = TldPremium::where('registrar_id', $registrar->id)
                ->where('extension', $ext)
                ->where('is_active', true)
                ->where('is_premium', true)
                ->max('max_premium_character');

            if ($maxTier) {
                return response()->json([
                    'success' => false,
                    'message' => "{$domainName} bukan domain premium — premium {$ext} hanya untuk nama maksimal {$maxTier} karakter. Untuk nama ini, pesan lewat halaman Cek Domain biasa.",
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Harga untuk ekstensi ini belum tersedia. Silakan hubungi kami.']);
        }

        // Cross-check ke DNAMA (sumber SUNGGUHAN status premium & tersedia
        // di registry), bukan cuma dipercaya dari perhitungan panjang
        // karakter kita sendiri. Di-cache 10 menit -- domain-availability
        // di DNAMA punya rate limit ketat, jadi jangan dipanggil tiap
        // klik "Cek" untuk nama yang sama berulang-ulang.
        $verified = null;
        try {
            $verified = \Illuminate\Support\Facades\Cache::remember(
                "dnama-premium-check:{$domainName}",
                now()->addMinutes(10),
                fn () => (new \App\Services\Domain\DnamaService($registrar))->checkPremiumStatus($domainName)
            );
        } catch (\Throwable $e) {
            $verified = null; // API DNAMA bermasalah -- jatuh balik ke tebakan tingkatan karakter di bawah, jangan sampai halaman ini ikut gagal cuma karena cross-check-nya gagal.
        }

        $isPremium = (bool) $premium->is_premium;
        $premiumVerifiedByRegistry = false;

        if ($verified && $verified['success']) {
            // DNAMA bilang TIDAK tersedia padahal RDAP bilang tersedia/
            // tidak pasti -- percaya DNAMA, karena dialah yang benar-benar
            // memegang data registrasi .id (RDAP cuma bootstrap publik).
            if ($verified['available'] === false) {
                return response()->json(['success' => true, 'domain' => $domainName, 'available' => false, 'message' => "{$domainName} sudah terdaftar."]);
            }

            $isPremium = $verified['is_premium'];
            $premiumVerifiedByRegistry = true;
        }

        $price = (float) ($premium->sell_register_price ?? 0);

        if ($price <= 0) {
            return response()->json(['success' => false, 'message' => 'Harga domain ini belum diisi admin. Silakan hubungi kami lewat tiket.']);
        }

        return response()->json([
            'success' => true,
            'domain' => $domainName,
            'available' => $available !== false,
            // RDAP kadang tidak bisa memastikan (timeout/tidak didukung
            // registry) -- ditandai di sini supaya UI bisa memberi
            // peringatan "belum bisa dipastikan" alih-alih diam-diam
            // menganggap tersedia.
            'unknown' => $available === null && ! $premiumVerifiedByRegistry,
            'tld_premium_id' => $premium->id,
            'is_premium' => $isPremium,
            // true kalau status premium di atas sudah dikonfirmasi
            // langsung dari DNAMA, false kalau cuma tebakan dari
            // tingkatan panjang karakter (API DNAMA sedang tidak bisa
            // dihubungi) -- dipakai UI untuk menampilkan catatan kecil.
            'premium_verified' => $premiumVerifiedByRegistry,
            'label' => $label,
            'extension' => $ext,
            'price' => $price,
            'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
        ]);
    }

    /**
     * Pilih baris tld_premiums yang tepat untuk SATU nama, berdasarkan
     * jumlah karakternya:
     *   - Kalau ada tingkatan premium (is_premium=true) yang batas
     *     karakternya (max_premium_character) >= panjang nama, pakai
     *     tingkatan TERKECIL yang masih cukup (paling murah yang valid).
     *   - Kalau nama lebih panjang dari semua tingkatan premium, jatuh
     *     balik ke baris reguler (is_premium=false) ekstensi itu.
     */
    private function resolvePremiumTier(Registrar $registrar, string $extension, int $labelLength): ?TldPremium
    {
        $rows = TldPremium::where('registrar_id', $registrar->id)
            ->where('extension', $extension)
            ->where('is_active', true)
            ->get();

        $tier = $rows->where('is_premium', true)
            ->filter(fn (TldPremium $r) => $r->max_premium_character !== null && $labelLength <= $r->max_premium_character)
            ->sortBy('max_premium_character')
            ->first();

        return $tier ?? $rows->firstWhere('is_premium', false);
    }
}