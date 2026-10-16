<?php

namespace App\Support;

use App\Services\Cart\CartService;

/**
 * Jumlah item untuk lencana keranjang di header.
 *
 * Keranjang disimpan di sesi domain CLIENT, jadi halaman di domain PUBLIK tidak
 * bisa membacanya. Domain client membagikan jumlahnya lewat cookie sederhana di
 * domain induk (lihat App\Http\Middleware\ShareCartCount); halaman publik
 * membaca cookie itu. Hanya angka -- bukan isi keranjang.
 */
class CartBadge
{
    public const COOKIE = 'cart_count';

    /** Batas tampilan: lencana cukup menunjukkan "ada isinya". */
    public const MAX = 99;

    public static function count(): int
    {
        $request = request();

        if ($request->getHost() === config('portal_domains.client')) {
            return min(self::MAX, app(CartService::class)->count());
        }

        return max(0, min(self::MAX, (int) $request->cookie(self::COOKIE, 0)));
    }

    /**
     * Domain tempat cookie dibagikan: domain publik, bila domain client adalah
     * subdomainnya (satucloudhosting.com dan member.satucloudhosting.com).
     * Null bila kedua domain tidak berbagi domain induk -- lencana di halaman
     * publik lalu tidak ditampilkan (tidak ada cookie lintas-situs).
     */
    public static function cookieDomain(): ?string
    {
        $public = strtolower((string) config('portal_domains.public'));
        $client = strtolower((string) config('portal_domains.client'));

        return $public !== '' && str_ends_with($client, '.'.$public) ? $public : null;
    }
}
