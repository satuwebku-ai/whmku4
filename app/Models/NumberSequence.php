<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NumberSequence extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'next_value'];

    protected function casts(): array
    {
        return ['next_value' => 'integer'];
    }

    public static function next(string $key, int $initial = 1): int
    {
        return DB::transaction(function () use ($key, $initial) {
            // insertOrIgnore membuat inisialisasi aman ketika dua request
            // pertama datang bersamaan; setelah itu row dikunci sebelum
            // nomor diambil sehingga tidak ada nomor ganda.
            static::query()->insertOrIgnore([
                'key' => $key,
                'next_value' => $initial,
            ]);

            $sequence = static::where('key', $key)->lockForUpdate()->firstOrFail();
            $value = (int) $sequence->next_value;
            $sequence->increment('next_value');
            return $value;
        });
    }

    /**
     * Ambil nomor berikutnya tetapi jangan pernah mundur melewati nomor
     * legacy yang sudah ada. Dipakai oleh generator yang ditambahkan setelah
     * tabel lama berisi data.
     */
    public static function nextAtLeast(string $key, int $minimum): int
    {
        return DB::transaction(function () use ($key, $minimum) {
            static::query()->insertOrIgnore([
                'key' => $key,
                'next_value' => $minimum,
            ]);

            $sequence = static::where('key', $key)->lockForUpdate()->firstOrFail();
            if ((int) $sequence->next_value < $minimum) {
                $sequence->update(['next_value' => $minimum]);
            }

            $value = (int) $sequence->next_value;
            $sequence->increment('next_value');

            return $value;
        });
    }
}
