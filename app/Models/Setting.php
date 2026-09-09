<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'is_encrypted'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    private const CACHE_KEY = 'app_settings';

    protected static function booted(): void
    {
        // Cache dibersihkan setiap ada perubahan supaya nilai di frontend
        // langsung ikut berubah tanpa perlu clear cache manual.
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Semua setting sebagai array [key => value], di-cache.
     */
    public static function all(...$args): mixed
    {
        if ($args) {
            return parent::all(...$args);
        }

        $rows = static::cachedRows();

        return array_map(fn ($row) => static::decryptRow($row), $rows);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $rows = static::cachedRows();
        $value = static::decryptRow($rows[$key] ?? null);

        return $value ?? $default;
    }

    /**
     * $encrypted default-nya SENGAJA `false` — supaya SEMUA pemanggilan
     * put() yang sudah ada di seluruh aplikasi (puluhan tempat) tetap
     * berperilaku identik seperti sebelumnya, tidak ada yang tiba-tiba
     * ikut terenkripsi tanpa diminta.
     */
    public static function put(string $key, mixed $value, string $group = 'general', bool $encrypted = false): void
    {
        static::updateOrCreate(['key' => $key], [
            'value' => ($encrypted && filled($value)) ? encrypt($value) : $value,
            'group' => $group,
            'is_encrypted' => $encrypted,
        ]);
    }

    /**
     * Cuma menyimpan array biasa (key => [value, is_encrypted]) ke
     * cache — BUKAN koleksi objek Eloquent penuh. Versi sebelumnya
     * (Cache::rememberForever(...)->keyBy('key') tanpa ->toArray())
     * menyimpan model Eloquent utuh ke cache, yang jauh lebih berat
     * diserialisasi PHP setiap baca/tulis — apalagi kalau driver cache-
     * nya 'database' (bukan file/redis), ini sempat bikin server
     * kehabisan memori (fatal error) di server produksi.
     */
    private static function cachedRows(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->get(['key', 'value', 'is_encrypted'])
            ->mapWithKeys(fn ($row) => [$row->key => ['value' => $row->value, 'is_encrypted' => $row->is_encrypted]])
            ->toArray());
    }

    private static function decryptRow(?array $row): mixed
    {
        if (! $row) {
            return null;
        }

        if ($row['is_encrypted'] && filled($row['value'])) {
            try {
                return decrypt($row['value']);
            } catch (\Throwable $e) {
                // Ciphertext rusak atau APP_KEY pernah berubah -- lebih
                // aman kembalikan kosong daripada melempar error yang
                // bisa merusak seluruh halaman yang memanggil Setting::get().
                return null;
            }
        }

        return $row['value'];
    }

    /**
     * Simpan banyak setting sekaligus dalam satu grup.
     */
    public static function putMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }

        static::flushCache();
    }
}
