<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;

    /**
     * Kata yang tidak boleh dipakai sebagai slug halaman.
     *
     * Sejak URL halaman dipindah ke root (tanpa awalan /p/), setiap slug
     * baru punya risiko bentrok dengan route bawaan aplikasi — kalau
     * seseorang membuat halaman berjudul "Hosting", judul itu akan
     * merebut alamat yang sama dengan halaman katalog hosting yang asli.
     * Daftar ini mencegah itu terjadi sejak awal, dengan pesan yang jelas,
     * daripada halaman diam-diam tidak bisa diakses tanpa penjelasan.
     */
    public const RESERVED_SLUGS = [
        'admin', 'client', 'hosting', 'cek-domain', 'keranjang', 'chat',
        'p', 'announcements', 'payment', 'storage', 'build', 'vendor',
        'api', 'login', 'register', 'logout', 'dashboard', 'home',
        'robots.txt', 'sitemap.xml', 'favicon.ico',
    ];

    public static function isReservedSlug(string $slug): bool
    {
        return in_array(Str::slug($slug), static::RESERVED_SLUGS, true);
    }

    protected $fillable = [
        'title', 'slug', 'content', 'meta_title', 'meta_description',
        'meta_keywords', 'og_image', 'noindex', 'is_published',
        'show_in_footer', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'noindex' => 'boolean',
            'is_published' => 'boolean',
            'show_in_footer' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page) {
            if (blank($page->slug)) {
                $page->slug = static::uniqueSlug($page->title, $page->id);
            } else {
                $page->slug = Str::slug($page->slug);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'halaman';
        $slug = $base;
        $i = 2;

        // Slug otomatis (judul dikosongkan) juga tidak boleh jatuh ke kata
        // terlarang — kalau ada yang membuat halaman berjudul "Admin" tanpa
        // mengisi slug manual, slug-nya digeser jadi "admin-2", bukan gagal
        // diam-diam.
        while (
            static::isReservedSlug($slug)
            || static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Judul untuk tag <title> — pakai meta_title kalau diisi.
     */
    public function getSeoTitleAttribute(): string
    {
        // Dipaksa string: pada form "Tambah Halaman" model masih kosong,
        // sehingga title/meta_title bernilai null.
        return (string) ($this->meta_title ?: $this->title ?: '');
    }

    public function getSeoDescriptionAttribute(): string
    {
        return (string) ($this->meta_description
            ?: Str::limit(strip_tags((string) $this->content), 155));
    }

    public function getUrlAttribute(): string
    {
        return route('page.show', (string) $this->slug);
    }
}
