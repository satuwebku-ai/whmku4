<?php

namespace App\Http\Middleware;

use App\Support\CartNotice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Di domain PUBLIK: bila alamat membawa ?cart_notice= (galat dari keranjang di
 * domain client), buka, masukkan ke sesi publik sebagai flash + old input
 * -- sehingga toast dan isian form yang sudah ada langsung bekerja -- lalu
 * arahkan ke alamat yang bersih. Payload rusak atau basi diabaikan begitu saja.
 */
class RestoreCartNotice
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $request->query->has(CartNotice::PARAM)) {
            return $next($request);
        }

        $token = $request->query(CartNotice::PARAM);
        $notice = is_string($token) ? CartNotice::open($token, $request) : null;

        $clean = $request->fullUrlWithoutQuery([CartNotice::PARAM]);
        $redirect = redirect()->to($clean);

        if ($notice !== null) {
            $redirect->with('error', $notice['message'])->withInput($notice['input']);
        }

        return $redirect;
    }
}
