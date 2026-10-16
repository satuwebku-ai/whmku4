<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengganti CSRF untuk titik serah-terima publik -> client.
 *
 * Form "pesan" di domain publik mengirim POST ke keranjang di domain client.
 * Token CSRF milik sesi publik tidak dikenal sesi client, jadi route-route
 * ini dikecualikan dari CSRF dan dijaga di sini: permintaan hanya diterima
 * kalau header Origin (atau Referer, kalau Origin tidak ada) berasal dari
 * domain publik atau domain client. Situs lain tidak bisa memasukkan barang
 * ke keranjang pengunjung kita.
 *
 * Dampak operasi ini kecil (hanya mengisi keranjang di sesi si pengunjung;
 * pembayaran tetap lewat checkout yang diproteksi CSRF), tapi pemeriksaan ini
 * menjaga agar keranjang tidak bisa diisi dari luar.
 *
 * Catatan: cookie sesi client baru ikut terkirim pada POST dari domain publik
 * bila kedua domain satu "site" (subdomain dari domain yang sama, seperti
 * satucloudhosting.com dan member.satucloudhosting.com). Itu sudah menjadi
 * asumsi konfigurasi portal_domains.
 */
class RequirePortalOrigin
{
    /**
     * Path (di domain client) yang dikecualikan dari CSRF. HARUS sama dengan
     * route yang memakai middleware ini -- dijaga oleh RequirePortalOriginTest.
     */
    public const HANDOFF_PATHS = [
        'store/keranjang/produk',
        'store/keranjang/lisensi',
        'store/keranjang/domain-premium',
        'store/keranjang/domain-premium-custom',
        'store/keranjang/domain',
        'store/keranjang/transfer-domain',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::isPortalOrigin($request)) {
            return redirect()->route('cart.index')
                ->with('error', 'Permintaan tidak dikenali. Silakan pilih produk langsung dari situs kami.');
        }

        return $next($request);
    }

    public static function isPortalOrigin(Request $request): bool
    {
        $source = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        $host = $source ? parse_url($source, PHP_URL_HOST) : null;

        if (! is_string($host) || $host === '') {
            return false;
        }

        return in_array(strtolower($host), [
            strtolower((string) config('portal_domains.public')),
            strtolower((string) config('portal_domains.client')),
        ], true);
    }

    /** Apakah permintaan ini datang dari halaman di domain PUBLIK? */
    public static function fromPublicSite(Request $request): bool
    {
        $source = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        $host = $source ? parse_url($source, PHP_URL_HOST) : null;

        return is_string($host)
            && strtolower($host) === strtolower((string) config('portal_domains.public'));
    }
}
