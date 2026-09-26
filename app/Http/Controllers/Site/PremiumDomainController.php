<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Registrar;
use App\Services\Domain\DomainRegistrarFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PremiumDomainController extends Controller
{
    /**
     * Ekstensi keluarga .id -- PANDI menetapkan tingkat harga premium
     * TETAP berdasarkan jumlah karakter, jadi bisa ditampilkan sebagai
     * daftar harga langsung (diambil live dari DNAMA lewat
     * listCustomerTldPricings(), sudah ada sejak halaman Harga
     * Reseller/Sub-Reseller admin). Beda dengan TLD generik di bawah,
     * yang harga premiumnya per-nama, bukan daftar tetap.
     */
    private const ID_FAMILY = [
        '.id', '.co.id', '.my.id', '.ac.id', '.sch.id',
        '.or.id', '.web.id', '.biz.id', '.ponpes.id',
    ];

    /**
     * Ekstensi generik yang DNAMA izinkan dicek/dipesan sebagai domain
     * premium (lewat daftarnama.id/reseller/premium-domains), tapi
     * harganya per-nama -- TIDAK ada daftar harga tetap seperti
     * keluarga .id di atas. Alur DNAMA sendiri memprosesnya manual
     * (bukan lewat Reseller API), jadi di sini cuma ditawarkan sebagai
     * "cek dulu, lalu pesan lewat tiket".
     */
    private const GENERIC_EXTENSIONS = [
        '.com', '.org', '.net', '.asia', '.biz', '.info', '.xyz', '.co',
        '.tv', '.name', '.mobi', '.cc', '.education', '.institute',
        '.foundation', '.store', '.travel', '.com.my',
    ];

    public function index(): View
    {
        return view('public.catalog.domain-premium', $this->data());
    }

    public function indexBootstrap(): View
    {
        return view('public.catalog.domain-premium', $this->data());
    }

    private function data(): array
    {
        $idFamily = $this->idFamilyPricing();
        $genericExtensions = self::GENERIC_EXTENSIONS;

        return compact('idFamily', 'genericExtensions');
    }

    /**
     * Ambil harga premium keluarga .id langsung dari DNAMA, dicache 1 jam
     * -- daftar harga PANDI jarang berubah dalam hitungan menit, jadi
     * tidak perlu memanggil API di setiap kunjungan halaman publik.
     */
    private function idFamilyPricing(): array
    {
        return Cache::remember('public.premium-domain.id-family', 3600, function () {
            $registrar = Registrar::where('provider', 'dnama')->where('is_active', true)->first();

            if (! $registrar) {
                return ['rows' => [], 'error' => null];
            }

            try {
                $service = DomainRegistrarFactory::make($registrar);

                if (! method_exists($service, 'listCustomerTldPricings')) {
                    return ['rows' => [], 'error' => null];
                }

                $result = $service->listCustomerTldPricings();

                if (! $result['success']) {
                    return ['rows' => [], 'error' => $result['message']];
                }

                return ['rows' => $this->groupIdFamilyRows($result['raw']['data'] ?? []), 'error' => null];
            } catch (\Throwable $e) {
                return ['rows' => [], 'error' => $e->getMessage()];
            }
        });
    }

    /**
     * Kelompokkan baris mentah DNAMA jadi {ekstensi => [reguler, premium...]}.
     *
     * DNAMA mengirim BARIS TERPISAH untuk varian premium dari ekstensi
     * yang sama (lihat catatan serupa di DnamaService::listTlds()) --
     * dibedakan lewat is_premium + max_premium_character, bukan lewat
     * nama field terpisah.
     */
    private function groupIdFamilyRows(array $rows): array
    {
        $wanted = self::ID_FAMILY;
        $out = [];

        foreach ($rows as $row) {
            $ext = $row['tld'] ?? null;

            if (! $ext || ! in_array($ext, $wanted, true)) {
                continue;
            }

            $oneYear = collect($row['pricings'] ?? [])->firstWhere('duration', 1);
            $isPremium = (bool) ($row['is_premium'] ?? false);
            $maxChar = $row['max_premium_character'] ?? null;

            $out[$ext][] = [
                'label' => $isPremium
                    ? $ext . ($maxChar ? " ({$maxChar} karakter) Premium" : ' Premium')
                    : $ext,
                'is_premium' => $isPremium,
                'max_premium_character' => $maxChar,
                'register' => $oneYear['register_price'] ?? null,
                'renew' => $oneYear['renewal_price'] ?? null,
                'transfer' => $oneYear['transfer_price'] ?? null,
                'currency' => $row['currency'] ?? 'IDR',
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
        $data = $request->validate([
            'domain_name' => ['required', 'string', 'max:255'],
        ]);

        $domain = strtolower(trim($data['domain_name']));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $ext = '.' . Str::after($domain, '.');

        if (! str_contains($domain, '.') || ! in_array($ext, self::GENERIC_EXTENSIONS, true)) {
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
