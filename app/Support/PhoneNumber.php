<?php

namespace App\Support;

/**
 * Normalisasi nomor telepon ke format internasional "+<kode negara><nomor>".
 *
 *  0812-3456-7890   → +6281234567890   (awalan 0 = Indonesia)
 *  6281234567890    → +6281234567890
 *  812345678        → +62812345678     (awalan 8 = seluler Indonesia tanpa 0)
 *  +60 12-345 6789  → +60123456789     (sudah internasional, tidak diubah)
 *  0060123456789    → +60123456789     (awalan 00 = akses internasional)
 */
class PhoneNumber
{
    /** Format longgar yang boleh diketik pengguna. */
    public const INPUT_REGEX = '/^\+?[0-9][0-9\s\-().]{5,28}$/';

    public static function digits(string $number): string
    {
        $number = trim($number);
        $hasPlus = str_starts_with($number, '+');
        $digits = preg_replace('/\D/', '', $number) ?? '';

        if ($digits === '') {
            return '';
        }

        if ($hasPlus) {
            return $digits;
        }

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return '62' . ltrim($digits, '0');
        }

        if (str_starts_with($digits, '62') || ! str_starts_with($digits, '8')) {
            return $digits;
        }

        return '62' . $digits;
    }

    /**
     * Kembalikan "+<digit>" atau null kalau panjangnya di luar 8-15 digit
     * (batas E.164 praktis).
     */
    public static function normalize(?string $number): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        $digits = static::digits($number);
        $len = strlen($digits);

        return ($len >= 8 && $len <= 15) ? '+' . $digits : null;
    }
}
