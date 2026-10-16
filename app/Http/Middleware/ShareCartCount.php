<?php

namespace App\Http\Middleware;

use App\Services\Cart\CartService;
use App\Support\CartBadge;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membagikan JUMLAH item keranjang ke domain publik (cookie di domain induk)
 * supaya lencana keranjang di header halaman publik akurat. Dipasang di grup
 * domain client; cookie hanya ditulis saat nilainya berubah.
 */
class ShareCartCount
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $domain = CartBadge::cookieDomain();

        if ($domain !== null) {
            $count = min(CartBadge::MAX, app(CartService::class)->count());

            if ((int) $request->cookie(CartBadge::COOKIE, -1) !== $count) {
                Cookie::queue(Cookie::make(
                    CartBadge::COOKIE, (string) $count, 60 * 24 * 30, '/', $domain,
                    $request->isSecure(), true, false, 'lax'
                ));
            }
        }

        return $response;
    }
}
