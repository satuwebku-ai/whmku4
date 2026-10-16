<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Halaman yang tidak ada di situs publik (satucloudhosting.com) dikembalikan
 * ke beranda, bukan menampilkan halaman 404.
 *
 * Halaman yang memang ada (menu, blog, pengumuman, halaman CMS, dll.) tidak
 * tersentuh -- ini hanya jaring pengaman untuk URL yang berujung 404.
 *
 * Sengaja TIDAK berlaku untuk:
 *  - subdomain admin & member (tetap 404 seperti biasa),
 *  - request selain GET/HEAD dan request JSON/AJAX (API, webhook, chat),
 *  - aset & endpoint teknis (file berekstensi, branding/, vendor/, chat/, ...)
 *    supaya gambar/CSS/JS yang hilang tidak "berhasil" memuat beranda.
 */
class PublicNotFoundRedirect
{
    /** Awalan path yang tidak boleh dialihkan. */
    private const SKIP_PREFIXES = [
        'branding', 'banner-image', 'vendor', 'storage', 'build',
        'chat', 'payment', 'webhook', 'csp-report', 'up',
    ];

    public static function shouldRedirect(Request $request): bool
    {
        if ($request->getHost() !== config('portal_domains.public')) {
            return false;
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return false;
        }

        $path = trim($request->path(), '/');

        // Beranda sendiri tidak boleh dialihkan ke dirinya (cegah loop).
        if ($path === '' || $path === '/') {
            return false;
        }

        if (pathinfo($path, PATHINFO_EXTENSION) !== '') {
            return false;
        }

        $first = strtok($path, '/');

        return ! in_array($first, self::SKIP_PREFIXES, true);
    }

    public static function response(): RedirectResponse
    {
        // 302 (sementara), bukan 301: halaman yang nanti dibuat admin dengan
        // slug yang sama tidak akan "tersangkut" di cache browser.
        return redirect()->route('home', [], 302);
    }
}
