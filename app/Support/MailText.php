<?php

namespace App\Support;

/**
 * Menetralkan teks yang diketik pengguna sebelum masuk ke email.
 *
 * Email sistem dirender dari Markdown (MailMessage::line). Blade sudah
 * meng-escape HTML, tetapi sintaks Markdown tetap hidup: nama klien
 * "[Verifikasi akun](https://situs-palsu)" menjadi tautan di email yang
 * tampak resmi dari sistem, "![x](https://pelacak/p.png)" menjadi gambar
 * pelacak, dan baris "[ACTION:Klik:https://...]" di dalam nilai bisa
 * menjadi tombol aksi palsu karena isi templat dipecah per baris.
 *
 * Pakai untuk NILAI dari pengguna saja, jangan untuk teks templat atau
 * konten yang memang ditulis admin sebagai Markdown.
 */
class MailText
{
    /**
     * Satu baris polos: tanpa baris baru atau karakter kontrol, dipangkas.
     * Cocok untuk WhatsApp/log dan untuk nilai yang tidak boleh memecah
     * baris (mis. baris "Label: nilai" di rincian).
     */
    public static function inline(?string $value, int $max = 300): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200F}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', ' ', (string) $value) ?? '';
        $value = trim(preg_replace('/\s{2,}/u', ' ', $value) ?? '');

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 1) . '…' : $value;
    }

    /**
     * Aman dimasukkan ke email Markdown. Tampil persis seperti teks aslinya
     * (garis miring terbalik di depan tanda Markdown tidak terlihat di
     * email HTML), tetapi tidak lagi menjadi tautan, gambar, judul, atau
     * tombol aksi. Baris baru dipertahankan untuk teks panjang.
     */
    public static function markdown(?string $value, int $max = 5000): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}-\x{200F}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', '', $value) ?? '';
        $value = preg_replace('/\n{3,}/', "\n\n", trim($value)) ?? '';

        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max - 1) . '…';
        }

        // Tanda Markdown inline. "<" dan "&" tidak perlu: Blade sudah
        // meng-escape HTML, dan mengescape-nya lagi merusak tampilan.
        $value = preg_replace('/([\\\\`*_\\[\\]()#!|~])/', '\\\\$1', $value) ?? '';

        // Penanda daftar di awal baris ("- ", "+ ", "1. ").
        $value = preg_replace('/^(\s*)([-+])(\s)/m', '$1\\\\$2$3', $value) ?? '';

        return preg_replace('/^(\s*)(\d+)([.)])(\s)/m', '$1$2\\\\$3$4', $value) ?? '';
    }
}
