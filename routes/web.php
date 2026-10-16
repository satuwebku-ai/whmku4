<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin and client portals
|--------------------------------------------------------------------------
| Portal routes remain in separate modules and are served only from their
| configured subdomains. Legacy /admin and /client URLs redirect there.
*/
Route::domain(config('portal_domains.admin'))
    ->name('admin.')
    ->group(base_path('routes/admin.php'));

Route::domain(config('portal_domains.admin'))->group(function () {
    Route::post('csp-report', \App\Http\Controllers\CspReportController::class)
        ->middleware('throttle:60,1');
    Route::any('admin/{path?}', function (Request $request, ?string $path = null) {
        $target = 'https://'.config('portal_domains.admin').'/'.ltrim((string) $path, '/');
        if ($request->getQueryString()) {
            $target .= '?'.$request->getQueryString();
        }

        return redirect()->away($target, 308);
    })->where('path', '.*');
    Route::any('{path?}', static fn () => abort(404))->where('path', '.*');
});

// ShareCartCount: membagikan jumlah item keranjang ke domain publik (lencana header).
Route::domain(config('portal_domains.client'))
    ->middleware(\App\Http\Middleware\ShareCartCount::class)
    ->name('client.')
    ->group(base_path('routes/client.php'));

Route::domain(config('portal_domains.client'))->middleware(\App\Http\Middleware\ShareCartCount::class)->group(function () {
    Route::post('csp-report', \App\Http\Controllers\CspReportController::class)
        ->middleware('throttle:60,1');

    // Keranjang & pemesanan di bawah /store (routes/cart.php). Prefix wajib:
    // portal client sudah memakai /vps dan /vps/{vps}. Harus didaftarkan
    // sebelum catch-all 404 di bawah.
    // UseClientThemeForStore: untuk klien login, halaman keranjang memakai
    // view dari tema Client (CSS-nya cocok dengan layout client).
    Route::prefix('store')
        ->middleware(\App\Http\Middleware\UseClientThemeForStore::class)
        ->group(base_path('routes/cart.php'));

    Route::any('client/{path?}', function (Request $request, ?string $path = null) {
        $target = 'https://'.config('portal_domains.client').'/'.ltrim((string) $path, '/');
        if ($request->getQueryString()) {
            $target .= '?'.$request->getQueryString();
        }

        return redirect()->away($target, 308);
    })->where('path', '.*');
    Route::any('{path?}', static fn () => abort(404))->where('path', '.*');
});

/*
|--------------------------------------------------------------------------
| Public website: CMS, public content, and store browsing
|--------------------------------------------------------------------------
| The public host serves content (home, blog, pages, announcements, knowledge
| base, promo, chat widget, webhooks) and the store's BROWSING pages: catalog,
| licenses, and domain checks, regular and premium (routes/store.php). Once
| a visitor chooses something, the forms post to the cart on the client host
| (routes/cart.php, under /store). The CMS slug catch-all in routes/public.php
| must remain last.
*/
// RestoreCartNotice: galat dari keranjang (domain client) dibawa kembali ke halaman publik asal.
Route::domain(config('portal_domains.public'))->middleware(\App\Http\Middleware\RestoreCartNotice::class)->group(function () {
    Route::any('admin/{path?}', function (Request $request, ?string $path = null) {
        if ($path === 'login') {
            return redirect()->route('home');
        }

        $target = 'https://'.config('portal_domains.admin').'/'.ltrim((string) $path, '/');
        if ($request->getQueryString()) {
            $target .= '?'.$request->getQueryString();
        }

        return redirect()->away($target, 308);
    })->where('path', '.*');

    Route::any('client/{path?}', function (Request $request, ?string $path = null) {
        $target = 'https://'.config('portal_domains.client').'/'.ltrim((string) $path, '/');
        if ($request->getQueryString()) {
            $target .= '?'.$request->getQueryString();
        }

        return redirect()->away($target, 308);
    })->where('path', '.*');

    Route::post('csp-report', \App\Http\Controllers\CspReportController::class)
        ->middleware('throttle:60,1')
        ->name('csp-report');

    // Menjelajah toko ada di sini; keranjang pindah ke portal client.
    require base_path('routes/store.php');

    // /keranjang lama (dulu di domain publik) -> keranjang di portal client.
    Route::get('keranjang', fn () => redirect()->away(route('cart.index'), 301));

    require base_path('routes/public.php');
});
