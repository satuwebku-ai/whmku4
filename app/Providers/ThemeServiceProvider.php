<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Membuat area Publik dan Client bisa ganti "tema" (kumpulan file blade)
 * lewat Admin > Pengaturan > Umum, tanpa perlu ubah satupun pemanggilan
 * view('public.xxx') / view('client.xxx') yang sudah ada di controller.
 *
 * Caranya: menambahkan folder tema terpilih sebagai lokasi pencarian
 * view tambahan (View::addLocation). Laravel akan coba tiap lokasi
 * urut dari prioritas TERTINGGI ke terendah untuk file
 * "public/xxx.blade.php" atau "client/xxx.blade.php" yang diminta.
 *
 * Urutan prioritas yang dibentuk (tinggi -> rendah):
 *   1. Tema Publik yang dipilih admin (kalau bukan "default")
 *   2. Tema Client yang dipilih admin (kalau bukan "default")
 *   3. Tema "default" (fallback tetap -- kalau tema pilihan cuma
 *      override sebagian file, sisanya jatuh balik ke sini)
 *
 * Ini SENGAJA dipisah dari AppServiceProvider supaya gampang
 * dinonaktifkan/diutak-atik sendiri tanpa menyentuh provider inti.
 */
class ThemeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        [$publicTheme, $clientTheme] = $this->resolveActiveThemes();

        // Lokasi ditambahkan dari prioritas RENDAH ke TINGGI, karena
        // View::addLocation() menaruh path baru di paling depan
        // (jadi yang ditambahkan PALING TERAKHIR = dicek PALING DULU).
        View::addLocation(resource_path('views/themes/default'));

        if ($clientTheme !== 'default') {
            View::addLocation(resource_path("views/themes/{$clientTheme}"));
        }

        if ($publicTheme !== 'default') {
            View::addLocation(resource_path("views/themes/{$publicTheme}"));
        }
    }

    /**
     * @return array{0: string, 1: string} [$publicTheme, $clientTheme]
     */
    private function resolveActiveThemes(): array
    {
        $available = config('themes', ['public' => [], 'client' => []]);

        $publicTheme = 'default';
        $clientTheme = 'default';

        try {
            // Schema::hasTable dijaga try/catch juga -- pada instalasi
            // baru sebelum `php artisan migrate` jalan, koneksi DB
            // sendiri bisa saja belum terkonfigurasi/tersedia.
            if (Schema::hasTable('settings')) {
                $publicTheme = Setting::get('public_template', 'default');
                $clientTheme = Setting::get('client_template', 'default');
            }
        } catch (\Throwable $e) {
            // DB belum siap (migrasi awal, `artisan key:generate`, dsb).
            // Pakai tema default saja, jangan sampai request gagal total.
        }

        // Kalau value di database ternyata mengarah ke tema yang sudah
        // dihapus foldernya / typo, jangan sampai seluruh situs 500 --
        // jatuh balik ke "default" yang pasti selalu ada.
        if (! array_key_exists($publicTheme, $available['public'] ?? [])) {
            $publicTheme = 'default';
        }

        if (! array_key_exists($clientTheme, $available['client'] ?? [])) {
            $clientTheme = 'default';
        }

        return [$publicTheme, $clientTheme];
    }
}
