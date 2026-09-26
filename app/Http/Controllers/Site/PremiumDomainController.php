<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\NavMenu;
use App\Models\Registrar;
use App\Models\TldPremium;
use App\Services\Domain\DomainRegistrarFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $genericExtensions = TldPremium::GENERIC_EXTENSIONS;
        $banners = \App\Models\PromoBanner::live()->forPage('domain_premium')->orderBy('sort_order')->get();

        return compact('idFamily', 'genericExtensions', 'banners');
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

            $out[$ext][] = [
                'label' => $row->label,
                'is_premium' => $row->is_premium,
                'max_premium_character' => $row->max_premium_character,
                'register' => $row->sell_register_price ?? $row->cost_register,
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
     * Cek satu nama domain generik (mis. "toko.com") LANGSUNG ke API
     * DNAMA (bukan RDAP seperti halaman Cek Domain biasa) -- supaya
     * flag is_premium ikut didapat. Dipanggil lewat AJAX dari halaman
     * Domain Premium.
     */
    public function check(Request $request): JsonResponse
    {
        $this->ensureActive();

        $data = $request->validate([
            'domain_name' => ['required', 'string', 'max:255'],
        ]);

        $domain = strtolower(trim($data['domain_name']));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $ext = '.' . Str::after($domain, '.');

        if (! str_contains($domain, '.') || ! in_array($ext, TldPremium::GENERIC_EXTENSIONS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Ekstensi tersebut belum kami dukung untuk pengecekan domain premium di halaman ini.',
            ]);
        }

        $registrar = Registrar::where('provider', 'dnama')->where('is_active', true)->first();

        if (! $registrar) {
            return response()->json(['success' => false, 'message' => 'Layanan pengecekan sedang tidak tersedia.']);
        }

        try {
            $service = DomainRegistrarFactory::make($registrar);
            $result = $service->checkAvailability([$domain]);

            if (! $result['success'] && empty($result['results'])) {
                return response()->json(['success' => false, 'message' => $result['message'] ?? 'Gagal mengecek domain.']);
            }

            $available = (bool) ($result['results'][$domain] ?? false);
            $isPremium = (bool) ($result['raw']['data']['is_premium'] ?? false);

            return response()->json([
                'success' => true,
                'domain' => $domain,
                'available' => $available,
                'is_premium' => $isPremium,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}