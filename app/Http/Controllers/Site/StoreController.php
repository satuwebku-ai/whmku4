<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartService;
use Illuminate\Contracts\View\View;

/**
 * Halaman utama Store di portal client (/store).
 *
 * Hanya berisi pintasan: Hosting, VPS, Domain, Lisensi, dan Keranjang. Halaman
 * jualannya sendiri tetap di domain publik (routes/store.php); keranjang di
 * portal client (routes/cart.php). Tamu boleh membuka halaman ini, sama seperti
 * keranjang.
 */
class StoreController extends Controller
{
    public function index(CartService $cart): View
    {
        return view('public.store.index', [
            'cartCount' => $cart->count(),
        ]);
    }
}
