<?php

namespace App\Http\Middleware;

use App\Support\ThemeRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman toko di portal client (keranjang) dirender di dalam layout portal
 * client -- untuk klien login maupun tamu -- jadi CSS-nya harus cocok dengan
 * TEMA CLIENT. View toko tinggal di
 * pohon tema Publik (resources/views/themes/public-themes/{tema}/public/...)
 * dan ditulis dengan CSS tema itu; tiap tema client memuat file CSS yang sama
 * dengan tema Publik berkunci sama. Karena itu, kalau tema Publik dan Client
 * yang aktif BERBEDA, pohon tema Publik milik tema CLIENT yang dipakai untuk
 * me-resolve view `public.*` (katalog, keranjang, lisensi, pembungkus toko,
 * dan partial yang di-include layout client).
 *
 */
class UseClientThemeForStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientTheme = ThemeRegistry::active('client');

        if ($clientTheme !== ThemeRegistry::active('public')) {
            $finder = View::getFinder();

            // Prioritas rendah -> tinggi (prependLocation menaruh di depan):
            // "default" sebagai fallback tetap, lalu tema client di atasnya.
            // Tanpa langkah "default", file yang tidak di-override tema client
            // akan jatuh ke tema Publik (CSS-nya tidak cocok).
            $finder->prependLocation(resource_path('views/themes/public-themes/default'));

            if ($clientTheme !== 'default') {
                $finder->prependLocation(resource_path("views/themes/public-themes/{$clientTheme}"));
            }

            // Buang cache hasil resolve view yang mungkin sudah terisi.
            $finder->flush();
        }

        return $next($request);
    }
}
