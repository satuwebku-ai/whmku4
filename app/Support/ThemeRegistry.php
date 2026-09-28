<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

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
}
