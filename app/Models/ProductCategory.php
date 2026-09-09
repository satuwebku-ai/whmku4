<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductCategory extends Model
{
    use HasFactory;

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
        static::saving(function (ProductCategory $category) {
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
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
