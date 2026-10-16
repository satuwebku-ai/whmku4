<?php

namespace App\Http\Middleware;

use App\Support\StoreUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman jualan di /store (portal member) adalah untuk klien yang login.
 * Tamu punya etalase sendiri di situs publik, jadi bila tamu membuka alamat
 * /store/... (tautan lama, bookmark, dsb.) ia dialihkan ke halaman publik yang
 * sepadan -- lengkap dengan parameter dan query string-nya.
 */
class RedirectStoreGuestsToPublic
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('client')->check() || ! $request->isMethod('GET')) {
            return $next($request);
        }

        $route = $request->route();
        $publicName = $route?->getName() ? StoreUrl::publicName($route->getName()) : null;

        if ($publicName === null) {
            return $next($request);
        }

        $target = route($publicName, $route->parameters());

        if ($request->getQueryString()) {
            $target .= '?'.$request->getQueryString();
        }

        return redirect()->away($target);
    }
}
