<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductType extends Model
{
    /** Perilaku yang tersedia. Logika server/tagihan/URL hanya mengenal dua ini. */
    /**
     * "Perilaku" (kind) sebuah jenis produk -- SATU-SATUNYA tempat nilai ini
     * didefinisikan. Seluruh kode lain bertanya lewat ProductGroup::isVps(),
     * scope ofKind(), atau ProductType::isVps(), bukan membandingkan teks
     * 'vps' sendiri. Jenis (nama, slug, ikon, warna) sepenuhnya data admin;
     * kind hanya memilih aturan server dan tagihannya.
     */
    public const KIND_HOSTING = 'hosting';

    public const KIND_VPS = 'vps';

    public const KINDS = [
        self::KIND_HOSTING => 'Hosting — server cPanel/WHM, tagihan invoice',
        self::KIND_VPS     => 'VPS / Cloud — server VM, tagihan deposit per jam',
    ];

    private const SECTIONS_CACHE_KEY = 'product_type_sections';

    /**
     * Pola regex segmen URL katalog ("hosting|vps|...") dari slug jenis
     * produk yang aktif. Dipakai routes/store.php supaya URL kategori/produk
     * mengikuti data admin, bukan daftar tetap. Kalau tabel belum ada
     * (mis. saat migrate pertama) atau kosong, dipakai pola bawaan.
     */
    public static function sectionPattern(): string
    {
        try {
            $slugs = \Illuminate\Support\Facades\Cache::remember(
                self::SECTIONS_CACHE_KEY, 300,
                fn () => static::where('is_active', true)->pluck('slug')->all()
            );
        } catch (\Throwable) {
            $slugs = [];
        }

        return $slugs ? implode('|', array_map('preg_quote', $slugs)) : 'hosting|vps';
    }

    protected static function booted(): void
    {
        $forget = fn () => \Illuminate\Support\Facades\Cache::forget(self::SECTIONS_CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }

    protected $fillable = ['slug', 'name', 'kind', 'icon', 'color', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ProductGroup::class, 'product_type_id');
    }

    public function isVps(): bool
    {
        return $this->kind === self::KIND_VPS;
    }

    public function kindLabel(): string
    {
        return $this->isVps() ? 'VPS / Cloud' : 'Hosting';
    }

    /** Perilaku jenis dengan slug ini (segmen URL katalog), atau null bila tidak ada. */
    public static function kindOfSlug(string $slug): ?string
    {
        return static::where('slug', $slug)->value('kind');
    }
}
