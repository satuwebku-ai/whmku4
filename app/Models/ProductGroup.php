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

    protected $fillable = ['name', 'slug', 'product_type_id', 'description', 'icon', 'is_active', 'sort_order'];

    /**
     * Jenis produk selalu ikut dimuat: segmen URL publik kategori ini
     * diambil dari product_types.slug (data yang diisi admin), bukan
     * ditentukan di kode.
     */
    protected $with = ['productType'];

    /** Apakah kategori ini berperilaku VPS/cloud? Dibaca dari jenis produknya (data admin). */
    public function isVps(): bool
    {
        return (bool) $this->productType?->isVps();
    }

    /**
     * Kategori dengan perilaku tertentu. Kategori tanpa jenis (data lama yang
     * belum dipetakan) dianggap hosting.
     */
    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereHas('productType', fn (Builder $t) => $t->where('kind', $kind))
            ->when($kind === ProductType::KIND_HOSTING, fn (Builder $w) => $w->orWhereDoesntHave('productType')));
    }

    /** Kebalikan scopeOfKind() untuk satu perilaku (mis. "bukan VPS"). */
    public function scopeNotOfKind(Builder $query, string $kind): Builder
    {
        return $query->whereDoesntHave('productType', fn (Builder $t) => $t->where('kind', $kind));
    }

    public function publicUrl(): string
    {
        return $this->productType
            ? \App\Support\StoreUrl::route('catalog.category', [$this->productType->slug, $this->slug])
            : \App\Support\StoreUrl::route('catalog.index');
    }

    /**
     * @param  bool  $public  paksa alamat etalase PUBLIK (mis. untuk mengembalikan galat
     *                        ke halaman publik asal), walau yang membuka klien login
     */
    public function productUrl(Product $product, bool $public = false): string
    {
        if (! $this->productType) {
            return $public ? route('catalog.index') : \App\Support\StoreUrl::route('catalog.index');
        }

        $params = [$this->productType->slug, $this->slug, $product->slug];

        return $public ? route('catalog.product', $params) : \App\Support\StoreUrl::route('catalog.product', $params);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductGroup $category) {
            // Kategori yang dibuat tanpa memilih jenis (seeder, impor, dsb)
            // otomatis memakai jenis hosting pertama.
            if (blank($category->product_type_id)) {
                $category->product_type_id = ProductType::where('kind', ProductType::KIND_HOSTING)
                    ->orderBy('sort_order')->value('id');
            }

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

    /** Jenis produk (data di database) yang dipilih admin untuk kategori ini. */
    public function productType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
