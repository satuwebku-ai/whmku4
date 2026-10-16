<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Teks section beranda (Keunggulan & Ajakan) yang bisa diedit dari dashboard
 * CMS. Tersimpan di tabel settings, jadi mengubah teks tidak perlu menyentuh
 * kode atau deploy.
 *
 * Kalau belum pernah diubah, semua view memakai teks bawaan masing-masing
 * tema -- sehingga tema yang punya teks khusus (mis. NamaHost) tidak berubah
 * sampai admin sengaja menyimpan teks baru.
 */
class HomeContent
{
    public const MAX_BENEFITS = 8;

    public const DEFAULT_BENEFITS = [
        ['title' => 'Aktif Otomatis',     'desc' => 'Akun hosting dibuat otomatis begitu pembayaran masuk.'],
        ['title' => 'Aman & Terjaga',     'desc' => 'Sertifikat SSL tersedia, backup rutin, dan proteksi berlapis.'],
        ['title' => 'Dukungan Responsif', 'desc' => 'Tim support siap membantu lewat tiket dan chat.'],
        ['title' => 'Bayar Mudah',        'desc' => 'Transfer bank, e-wallet, kartu kredit, dan QRIS.'],
    ];

    public const DEFAULT_CTA = [
        'title'     => 'Siap memulai website-mu?',
        'text'      => 'Buat akun gratis, pilih paket, dan mulai online hari ini juga.',
        'primary'   => 'Lihat Paket Hosting',
        'secondary' => 'Daftar Gratis',
    ];

    public const DEFAULT_STEPS = [
        ['title' => 'Pilih domain',        'desc' => 'Cari nama yang cocok dan cek ketersediaannya langsung.'],
        ['title' => 'Pilih paket hosting', 'desc' => 'Sesuaikan paket dengan kebutuhan website Anda.'],
        ['title' => 'Bayar & online',      'desc' => 'Akun aktif otomatis setelah pembayaran diterima.'],
    ];

    public const MAX_STEPS = 6;

    /**
     * Daftar keunggulan. $fallback = bawaan tema (boleh memuat kunci tambahan
     * seperti 'icon' atau 'tile'). Kunci tambahan itu dipinjam dari $fallback
     * menurut urutan (berputar) supaya ikon tetap ada walau jumlah item
     * berbeda dari bawaan.
     *
     * @return array<int, array<string, string>>
     */
    public static function benefits(array $fallback): array
    {
        $stored = self::decode('home_benefits');
        $items = [];

        foreach ($stored as $row) {
            $title = trim((string) ($row['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $base = $fallback ? $fallback[count($items) % count($fallback)] : [];
            $items[] = array_merge($base, [
                'title' => $title,
                'desc'  => trim((string) ($row['desc'] ?? '')),
            ]);
        }

        return $items ?: $fallback;
    }

    /**
     * Teks kartu ajakan. Hanya kolom yang terisi yang menimpa bawaan tema.
     *
     * @return array{title:string,text:string,primary:string,secondary:string}
     */
    public static function cta(array $fallback): array
    {
        $stored = self::decode('home_cta');

        foreach (['title', 'text', 'primary', 'secondary'] as $key) {
            $value = trim((string) ($stored[$key] ?? ''));

            if ($value !== '') {
                $fallback[$key] = $value;
            }
        }

        return $fallback;
    }

    /**
     * Paragraf di bawah judul hero. Kosong = pakai teks bawaan tema.
     */
    public static function heroText(string $fallback): string
    {
        $value = trim((string) Setting::get('home_hero_text'));

        return $value !== '' ? $value : $fallback;
    }

    /**
     * Langkah "Mulai dalam 3 langkah" (tema NamaHost).
     *
     * @return array<int, array{title:string,desc:string}>
     */
    public static function steps(array $fallback): array
    {
        $items = [];

        foreach (self::decode('home_steps') as $row) {
            $title = trim((string) ($row['title'] ?? ''));

            if ($title !== '') {
                $items[] = ['title' => $title, 'desc' => trim((string) ($row['desc'] ?? ''))];
            }
        }

        return $items ?: $fallback;
    }

    private static function decode(string $key): array
    {
        $data = json_decode((string) Setting::get($key), true);

        return is_array($data) ? $data : [];
    }
}
