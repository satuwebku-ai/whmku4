<?php

namespace App\Support;

/**
 * Pilihan durasi registrasi sebuah TLD beserta hematnya dibanding beli per tahun.
 *
 * Harga tiap durasi diambil lewat $priceFor (Tld::priceForYears), jadi angka di
 * halaman publik selalu sama dengan yang dihitung keranjang dan checkout.
 * "Hemat" hanya muncul bila harga durasi itu benar-benar lebih murah daripada
 * harga 1 tahun × jumlah tahun.
 */
final class TldDurations
{
    /** Durasi yang ditawarkan di beranda (dibatasi min/max_years tiap TLD). */
    public const CANDIDATES = [1, 2, 3, 5];

    /**
     * @param callable(int): float $priceFor harga total untuk N tahun
     * @param list<int>|null $candidates
     * @return array<int, array{price: float, linear: float, saving: float, percent: int}>
     */
    public static function build(callable $priceFor, int $minYears, int $maxYears, ?array $candidates = null): array
    {
        $minYears = max($minYears, 1);
        $options = [];
        $oneYear = null;

        foreach ($candidates ?? self::CANDIDATES as $years) {
            if ($years < $minYears || $years > $maxYears) {
                continue;
            }

            $price = (float) $priceFor($years);
            if ($price <= 0) {
                continue;
            }

            $oneYear ??= (float) $priceFor(1);
            // Bila 1 tahun tidak dijual (min_years > 1), tidak ada pembanding linier.
            $linear = ($oneYear > 0 && $minYears === 1) ? $oneYear * $years : $price;
            $saving = max($linear - $price, 0.0);

            $options[$years] = [
                'price'   => $price,
                'linear'  => $linear,
                'saving'  => $saving,
                'percent' => $linear > 0 ? (int) floor($saving / $linear * 100) : 0,
            ];
        }

        return $options;
    }
}
