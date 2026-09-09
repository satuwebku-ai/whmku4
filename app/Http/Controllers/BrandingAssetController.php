<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Melayani logo, favicon, dan gambar banner LEWAT Laravel (bukan file
 * statis di folder public/) — supaya tidak bergantung sama sekali pada
 * folder mana yang benar-benar dilayani web server. Ini dibuktikan
 * berfungsi karena halaman 404 kustom kita sendiri bisa muncul, artinya
 * jalur ini (lewat index.php Laravel) pasti bisa diakses, apa pun
 * struktur foldernya di server.
 *
 * File sesungguhnya disimpan di storage/app/branding & storage/app/banners
 * (disk 'local', BUKAN 'public') — tidak butuh symlink storage/public
 * sama sekali, dan tidak butuh folder public/ yang benar-benar
 * disinkronkan ke luar.
 */
class BrandingAssetController extends Controller
{
    public function branding(string $filename)
    {
        return $this->serve('branding', $filename);
    }

    public function banner(string $filename)
    {
        return $this->serve('banners', $filename);
    }

    /**
     * Font Awesome (CSS + font) disajikan dari sini, BUKAN CDN — supaya
     * tampilan tidak kosong sama sekali kalau CDN pihak ketiga sedang
     * lambat/tidak bisa diakses. File disimpan persis meniru struktur
     * folder paket aslinya (css/ dan webfonts/ terpisah), supaya jalur
     * relatif di dalam file CSS-nya (../webfonts/...) tetap benar tanpa
     * perlu disunting sama sekali.
     */
    public function fontAwesomeCss(string $filename)
    {
        return $this->serveVendor('fontawesome/css', $filename, 'text/css');
    }

    public function fontAwesomeWebfont(string $filename)
    {
        return $this->serveVendor('fontawesome/webfonts', $filename, 'font/woff2');
    }

    public function tailwindBrowser()
    {
        return $this->serveVendor('tailwind', 'browser.js', 'application/javascript');
    }

    private function serveVendor(string $folder, string $filename, string $contentType)
    {
        abort_unless(preg_match('/^[\w.\-]+$/', $filename) === 1, 403);

        $path = "vendor-assets/{$folder}/{$filename}";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $filename, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=2592000', // 30 hari -- aset vendor jarang berubah
        ]);
    }

    private function serve(string $folder, string $filename)
    {
        // Cegah path traversal (mis. "../../.env") — nama file cuma
        // boleh huruf/angka/titik/underscore/strip, tidak boleh ada
        // pemisah folder sama sekali.
        abort_unless(preg_match('/^[\w.\-]+$/', $filename) === 1, 403);

        $path = "{$folder}/{$filename}";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $filename, [
            // Boleh disimpan cache browser lumayan lama — logo/banner
            // jarang berubah, dan kalaupun berubah, nama filenya juga
            // ikut berubah (pakai timestamp), jadi aman di-cache agresif.
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
