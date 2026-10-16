<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar tema diambil LANGSUNG dari folder (bukan dari config/), supaya:
 *  - tema baru cukup ditaruh foldernya, tanpa daftar manual;
 *  - tidak terpengaruh `php artisan config:cache` di server produksi
 *    (penyebab dropdown tetap cuma menampilkan "Default" setelah deploy).
 *
 * Struktur:
 *   resources/views/themes/public-themes/{key}/public/...
 *   resources/views/themes/client-themes/{key}/client/...
 * Label opsional: file theme.json di dalam folder {key}, mis. {"label":"Modern (Elegan)"}
 */
class ThemeRegistry
{
    /** @return array<string, array{label:string}> */
    public static function available(string $scope): array
    {
        $base = resource_path("views/themes/{$scope}-themes");
        $themes = [];

        if (File::isDirectory($base)) {
            foreach (File::directories($base) as $dir) {
                $key = basename($dir);
                $label = ucfirst($key);
                $meta = $dir . '/theme.json';

                if (File::exists($meta)) {
                    $json = json_decode(File::get($meta), true);
                    $label = $json['label'] ?? $label;
                }

                $themes[$key] = ['label' => $label];
            }
        }

        if (! isset($themes['default'])) {
            $themes = ['default' => ['label' => 'Default']] + $themes;
        }

        ksort($themes);

        return $themes;
    }

    /**
     * Key tema yang aktif untuk scope 'public' atau 'client'. Kalau pengaturan
     * belum ada, DB belum siap (migrasi awal), atau menunjuk ke tema yang
     * foldernya sudah dihapus, jatuh balik ke "default" supaya situs tidak 500.
     */
    public static function active(string $scope): string
    {
        $key = 'default';

        try {
            if (Schema::hasTable('settings')) {
                $key = (string) Setting::get("{$scope}_template", 'default');
            }
        } catch (\Throwable) {
            // DB belum siap -- pakai default.
        }

        return array_key_exists($key, self::available($scope)) ? $key : 'default';
    }

    /**
     * Layout induk untuk halaman toko (katalog, domain, lisensi, keranjang).
     *
     * Ditentukan oleh DOMAIN, bukan status login: di domain client (keranjang dan
     * checkout) selalu layout portal client dalam mode store -- klien login maupun
     * tamu -- supaya satu pengalaman belanja; di domain publik (etalase) layout
     * publik. Supaya CSS view toko cocok dengan layout client walau tema Publik
     * dan Client berbeda, view toko di domain client di-resolve dari tema Client
     * -- lihat App\Http\Middleware\UseClientThemeForStore.
     */
    public static function storeLayout(): string
    {
        return request()->getHost() === config('portal_domains.client') ? 'client.layout' : 'public.layout';
    }
}
