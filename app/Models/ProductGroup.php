<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductGroup extends Model
{
    use HasFactory;

    /**
     * Nama tabel dinyatakan eksplisit karena nama file migrasi historisnya
     * masih menyebut product_categories, sementara tabel aktual adalah
     * product_groups. Jangan mengganti nama migrasi pada database berjalan.
     */
    protected $table = 'product_groups';

    protected $fillable = ['name', 'slug', 'type', 'description', 'icon', 'is_active', 'sort_order'];

    /**
     * Segmen URL publik untuk kategori ini -- "vps" atau "hosting".
     * Dipakai supaya semua tautan otomatis benar tanpa tiap pemanggil
     * perlu tahu jenis kategorinya.
     */
    public function urlSection(): string
    {
        return ($this->type ?? 'hosting') === 'vps' ? 'vps' : 'hosting';
    }

    public function publicUrl(): string
    {
        return route('catalog.category', [$this->urlSection(), $this->slug]);
    }

    public function productUrl(Product $product): string
    {
        return route('catalog.product', [$this->urlSection(), $this->slug, $product->slug]);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductGroup $category) {
            if (blank($category->slug)) {
                $category->slug = static::uniqueSlug($category->name, $category->id);
            } else {
                $category->slug = Str::slug($category->slug);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function products(): HasMany
    {
        // Nama FK lama dipertahankan agar kompatibel dengan data dan relasi
        // produk yang sudah berjalan.
        return $this->hasMany(Product::class, 'product_category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
