<?php

namespace App\Support;

/**
 * Alamat halaman jualan yang sadar konteks: SATU permukaan per jenis pengunjung.
 *
 *  - Klien yang sudah login di portal member (member.*) memakai halaman Store
 *    miliknya sendiri di bawah /store (store.hosting, store.vps, ...).
 *  - Tamu, dan siapa pun di situs publik, tetap memakai halaman publik
 *    (catalog.index, catalog.vps, ...). Tidak ada yang berubah untuk mereka.
 *
 * View jualan dipakai bersama oleh kedua permukaan, jadi tautan di dalamnya
 * dibuat lewat StoreUrl::route() dengan NAMA ROUTE PUBLIK; kelas ini yang
 * memutuskan alamat mana yang keluar. Nama di luar peta diteruskan apa adanya.
 */
class StoreUrl
{
    /** Nama route publik => nama route Store di portal member. */
    public const MAP = [
        'catalog.index'        => 'store.hosting',
        'catalog.vps'          => 'store.vps',
        'catalog.category'     => 'store.category',
        'catalog.product'      => 'store.product',
        'license.index'        => 'store.license',
        'license.show'         => 'store.license.show',
        'domain.search'        => 'store.domain',
        'domains.transfer'     => 'store.transfer',
        'domain-premium.index' => 'store.domain-premium',
        'domain-premium.check' => 'store.domain-premium.check',
    ];

    /** True bila halaman sedang dibuka klien login di domain portal member. */
    public static function inMemberStore(): bool
    {
        return request()->getHost() === config('portal_domains.client')
            && auth('client')->check();
    }

    /**
     * @param  array<string, mixed>|string|int  $parameters
     */
    public static function route(string $publicName, mixed $parameters = [], bool $absolute = true): string
    {
        $name = self::inMemberStore() ? (self::MAP[$publicName] ?? $publicName) : $publicName;

        return route($name, $parameters, $absolute);
    }

    /** Nama route publik untuk padanan Store (null bila bukan route Store). */
    public static function publicName(string $storeName): ?string
    {
        $public = array_search($storeName, self::MAP, true);

        return $public === false ? null : $public;
    }
}
