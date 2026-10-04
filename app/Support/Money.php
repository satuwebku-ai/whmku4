<?php

namespace App\Support;

/**
 * Hitung uang dalam sen (integer) supaya penjumlahan/perbandingan tidak
 * terkena galat pecahan float (0.1 + 0.2 != 0.3). Kolom database tetap
 * DECIMAL(14,2); kelas ini hanya jembatan aman untuk aritmetikanya.
 */
class Money
{
    public static function cents(float|int|string|null $value): int
    {
        return (int) round(round((float) $value, 2) * 100);
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    /** $a >= $b dibandingkan dalam sen. */
    public static function gte(float|int|string|null $a, float|int|string|null $b): bool
    {
        return static::cents($a) >= static::cents($b);
    }

    public static function rupiah(float|int|string|null $value): string
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }
}
