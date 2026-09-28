<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\ThemeRegistry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Membuat area Publik dan Client bisa ganti "tema" (kumpulan file blade)
 * lewat Admin > Pengaturan > Umum, tanpa perlu ubah satupun pemanggilan
 * view('public.xxx') / view('client.xxx') yang sudah ada di controller.
 *
 * STRUKTUR FOLDER (SENGAJA dipisah total per scope -- lihat catatan
 * penting di bawah):
 *   resources/views/themes/public-themes/{key}/public/...
 *   resources/views/themes/client-themes/{key}/client/...
 *
 * Cara kerjanya: folder tema Publik yang aktif & folder tema Client
 * yang aktif ditambahkan sebagai lokasi pencarian view tambahan
 * (View::addLocation). Laravel akan coba tiap lokasi urut dari
 * prioritas TERTINGGI ke terendah untuk file yang diminta, mis.
 * "public/catalog/index.blade.php" untuk view('public.catalog.index').
 *
 * PENTING -- kenapa "public-themes" dan "client-themes" dipisah jadi
 * dua pohon folder sendiri (BUKAN satu folder tema berisi sub-folder
 * public/ + client/ sekaligus): kalau satu folder tema dipakai untuk
 * KEDUA scope, memilih tema itu untuk Publik SAJA akan otomatis ikut
 * "membocorkan" tampilan Client-nya juga (dan sebaliknya) begitu kedua
 * scope kebetulan pakai key yang sama -- karena $finder->prependLocation()
 * sifatnya global, bukan per-prefix. Dengan folder digabung total per
 * scope seperti sekarang, root yang ditambahkan untuk tema Publik
 * SECARA FISIK tidak punya folder "client/" di dalamnya (begitu juga
 * sebaliknya), jadi tidak mungkin ada kebocoran lintas-scope.
 *
 * Urutan prioritas yang dibentuk per scope (tinggi -> rendah):
 *   1. Tema yang dipilih admin untuk scope itu (kalau bukan "default")
 *   2. Tema "default" (fallback tetap -- kalau tema pilihan cuma
 *      override sebagian file, sisanya jatuh balik ke sini)
 */
class ThemeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        [$publicTheme, $clientTheme] = $this->resolveActiveThemes();

        // PENTING: pakai prependLocation(), BUKAN addLocation(). addLocation()
        // MENAMBAH di BELAKANG daftar (dicek paling akhir), sehingga tema
        // "default" yang ditambahkan lebih dulu akan selalu menang atas tema
        // pilihan admin -- itu penyebab tampilan tidak pernah berubah.
        // prependLocation() menaruh di DEPAN: yang dipanggil terakhir = dicek
        // pertama.
        $finder = View::getFinder();

        // Lokasi ditambahkan dari prioritas RENDAH ke TINGGI, karena
        // $finder->prependLocation() menaruh path baru di paling depan
        // (jadi yang ditambahkan PALING TERAKHIR = dicek PALING DULU).
        $finder->prependLocation(resource_path('views/themes/client-themes/default'));
        $finder->prependLocation(resource_path('views/themes/public-themes/default'));

        if ($clientTheme !== 'default') {
            $finder->prependLocation(resource_path("views/themes/client-themes/{$clientTheme}"));
        }

        if ($publicTheme !== 'default') {
            $finder->prependLocation(resource_path("views/themes/public-themes/{$publicTheme}"));
        }
    }

    /**
     * @return array{0: string, 1: string} [$publicTheme, $clientTheme]
     */
    private function resolveActiveThemes(): array
    {
        $available = [
            'public' => ThemeRegistry::available('public'),
            'client' => ThemeRegistry::available('client'),
        ];

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
