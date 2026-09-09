<?php

namespace App\Services\Image;

/**
 * Crop & resize gambar upload supaya PAS dengan rasio tampilan di
 * publik -- tanpa ini, admin bisa upload gambar rasio sembarang, lalu
 * CSS object-fit:cover di publik memotongnya secara tidak terduga
 * (bisa memotong bagian penting gambar/teks banner).
 *
 * Dipakai PHP GD bawaan (bukan package composer tambahan) supaya
 * tidak perlu instalasi ekstra di shared hosting cPanel yang sering
 * tidak bisa `composer install` ulang sembarangan.
 */
class ImageFitter
{
    /**
     * Crop tengah (center-crop) gambar sumber supaya persis $targetW x
     * $targetH -- sama seperti CSS object-fit:cover, tapi dilakukan
     * SEKALI saat upload, bukan tiap kali dirender di browser.
     */
    public static function cropToFit(string $sourcePath, string $destPath, int $targetW, int $targetH): bool
    {
        // Dicatat PALING AWAL, sebelum apa pun terjadi -- supaya kalau
        // log tetap kosong setelah ini, kita tahu PASTI fungsi ini
        // tidak pernah terpanggil sama sekali (bukan cuma gagal diam-diam).
        \Illuminate\Support\Facades\Log::info("ImageFitter: cropToFit() dipanggil, target {$targetW}x{$targetH}", ['source' => $sourcePath]);

        if (! extension_loaded('gd')) {
            \Illuminate\Support\Facades\Log::warning('ImageFitter: ekstensi GD tidak tersedia di server ini, gambar disalin apa adanya (tidak dipotong).');

            return copy($sourcePath, $destPath);
        }

        $info = @getimagesize($sourcePath);

        if (! $info) {
            \Illuminate\Support\Facades\Log::warning('ImageFitter: getimagesize() gagal membaca file — mungkin bukan gambar valid atau rusak.', ['source' => $sourcePath]);

            return copy($sourcePath, $destPath);
        }

        [$srcW, $srcH] = $info;
        $mime = $info['mime'];

        // Batas akal sehat -- gambar di atas ini (mis. salah unggah foto
        // kamera 50 megapiksel) TIDAK dipaksa diproses, karena bisa
        // membebani server bersama (shared hosting dipakai banyak akun
        // sekaligus). Dipakai gambar asli sebagai cadangan, dicatat ke
        // log supaya admin tahu kenapa hasilnya tidak terpotong rapi.
        if ($srcW * $srcH > 40_000_000) {
            \Illuminate\Support\Facades\Log::warning('ImageFitter: gambar terlalu besar untuk diproses aman di shared hosting, dipakai apa adanya.', [
                'source' => $sourcePath,
                'ukuran_asli' => "{$srcW}x{$srcH}",
            ]);

            return copy($sourcePath, $destPath);
        }

        // Gambar beresolusi besar butuh memori GD yang jauh lebih besar
        // dari ukuran filenya (bitmap mentah, bukan terkompresi) --
        // mis. foto 4000x3000px butuh ±48MB cuma untuk decode, padahal
        // batas default shared hosting sering 128-256MB dan sudah
        // dipakai proses lain. Dinaikkan SEMENTARA khusus permintaan
        // ini (bukan permanen), supaya gambar besar tidak diam-diam
        // gagal diproses dan jatuh ke gambar mentah tanpa dipotong.
        $estimasiKebutuhan = $srcW * $srcH * 4 * 2.2; // faktor 2.2 = ruang aman untuk overhead GD
        $batasSekarang = self::parseMemoryLimit(ini_get('memory_limit'));

        if ($batasSekarang > 0 && $estimasiKebutuhan > $batasSekarang) {
            $batasBaruMb = (int) ceil($estimasiKebutuhan / 1024 / 1024) + 32;
            @ini_set('memory_limit', $batasBaruMb . 'M');
        }

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png'  => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            default      => null,
        };

        if (! $src) {
            \Illuminate\Support\Facades\Log::warning('ImageFitter: gagal decode gambar (kemungkinan batas memori PHP terlampaui) — dipakai gambar ASLI TANPA dipotong sebagai cadangan.', [
                'source' => $sourcePath,
                'ukuran_asli' => "{$srcW}x{$srcH}",
                'mime' => $mime,
                'memory_limit_saat_ini' => ini_get('memory_limit'),
                'estimasi_kebutuhan_mb' => round($estimasiKebutuhan / 1024 / 1024),
            ]);

            return copy($sourcePath, $destPath);
        }

        // Hitung area crop di gambar SUMBER supaya rasionya sama persis
        // dengan target, baru diskalakan ke ukuran target.
        $srcRatio = $srcW / $srcH;
        $targetRatio = $targetW / $targetH;

        if ($srcRatio > $targetRatio) {
            // Sumber lebih "lebar" dari target -- potong kiri-kanan.
            $cropH = $srcH;
            $cropW = (int) round($srcH * $targetRatio);
            $cropX = (int) round(($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            // Sumber lebih "tinggi" dari target -- potong atas-bawah.
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($srcH - $cropH) / 2);
        }

        $dest = imagecreatetruecolor($targetW, $targetH);

        // Latar transparan dijaga untuk PNG -- supaya logo/gambar dengan
        // transparansi tidak berubah jadi kotak hitam.
        if ($mime === 'image/png') {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
            imagefilledrectangle($dest, 0, 0, $targetW, $targetH, $transparent);
        }

        imagecopyresampled($dest, $src, 0, 0, $cropX, $cropY, $targetW, $targetH, $cropW, $cropH);

        $result = match ($mime) {
            'image/jpeg' => imagejpeg($dest, $destPath, 88),
            'image/png'  => imagepng($dest, $destPath, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($dest, $destPath, 88) : imagejpeg($dest, $destPath, 88),
            default      => false,
        };

        imagedestroy($src);
        imagedestroy($dest);

        // SELALU dicatat, baik berhasil maupun gagal -- sebelumnya cuma
        // jalur gagal yang tercatat, sehingga log kosong itu ambigu
        // (bisa berarti "berhasil sempurna" ATAU "kode ini tidak pernah
        // dijalankan sama sekali"). Sekarang keduanya bisa dibedakan.
        \Illuminate\Support\Facades\Log::info('ImageFitter: ' . ($result ? 'berhasil' : 'GAGAL saat menyimpan hasil'), [
            'source' => $sourcePath,
            'dest' => $destPath,
            'ukuran_asli' => "{$srcW}x{$srcH}",
            'ukuran_target' => "{$targetW}x{$targetH}",
            'area_crop' => "{$cropW}x{$cropH} mulai dari ({$cropX},{$cropY})",
        ]);

        return $result;
    }

    /**
     * Ubah nilai memory_limit dari php.ini (mis. "256M", "1G", atau
     * "-1" untuk tanpa batas) jadi jumlah byte -- supaya bisa
     * dibandingkan dengan estimasi kebutuhan memori gambar.
     */
    private static function parseMemoryLimit(string|false $value): int
    {
        if ($value === false || $value === '-1') {
            return -1; // tanpa batas -- tidak perlu dinaikkan
        }

        $value = trim($value);
        $unit = strtoupper(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'G' => $number * 1024 * 1024 * 1024,
            'M' => $number * 1024 * 1024,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
