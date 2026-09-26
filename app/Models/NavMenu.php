<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route as RouteFacade;
use App\Models\Setting;

class NavMenu extends Model
{
    protected $fillable = [
        'parent_id', 'label', 'type', 'route_name', 'page_id', 'url',
        'open_in_new_tab', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'open_in_new_tab' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(NavMenu::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NavMenu::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    /**
     * Halaman bawaan sistem yang boleh dipilih untuk tipe "route".
     * Dibatasi ke daftar ini (bukan nama route bebas) supaya admin tidak
     * bisa memasukkan nama route yang tidak ada dan mematahkan menu.
     *
     * Keranjang (cart.index) SENGAJA tidak dimasukkan di sini -- itu
     * bagian dari proses checkout (dipicu tombol "Tambah ke Keranjang"
     * di halaman produk/domain, lewat ikon keranjang di header), bukan
     * halaman untuk dijelajah lewat menu navigasi. Mirip cart.php di
     * WHMCS yang punya jalurnya sendiri, terpisah dari menu situs.
     */
    public const BUILTIN_ROUTES = [
        'home' => 'Beranda',
        'catalog.index' => 'Katalog Hosting',
        'domain.search' => 'Cek Domain',
        'domain-premium.index' => 'Domain Premium',
        'announcements.index' => 'Pengumuman',
    ];

    public function page(): BelongsTo
    {
        // FK disebut eksplisit -- kolomnya TETAP page_id (tidak ikut
        // di-rename saat model Page jadi CmsPage), jadi tidak boleh
        // mengandalkan tebakan otomatis Eloquent (yang sekarang akan
        // menebak cms_page_id dan salah).
        return $this->belongsTo(CmsPage::class, 'page_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * URL tujuan menu ini, atau null kalau tujuannya tidak lagi valid
     * (mis. halaman terkait sudah dihapus atau di-draf-kan). Item dengan
     * URL null sengaja disembunyikan di layout publik, bukan ditampilkan
     * sebagai tautan mati.
     */
    public function getResolvedUrlAttribute(): ?string
    {
        return match ($this->type) {
            // Fitur yang punya toggle Aktif/Nonaktif tersendiri di
            // Pengaturan (mis. Domain Premium) -- kalau dimatikan, menu
            // yang menunjuk ke sana ikut disembunyikan otomatis, supaya
            // tidak ada link navbar yang mengarah ke halaman 404.
            'route' => ($this->route_name === 'domain-premium.index' && ! Setting::get('domain_premium_active', '1'))
                ? null
                : (($this->route_name && RouteFacade::has($this->route_name))
                    ? route($this->route_name)
                    : null),

            'page' => ($this->page && $this->page->is_published)
                ? route('page.show', $this->page->slug)
                : null,

            'url' => $this->url ?: null,

            default => null,
        };
    }

    /**
     * Nama pola route untuk menandai menu yang sedang aktif di navigasi
     * (mis. semua URL /hosting/* menyorot menu "Hosting").
     */
    public function getActivePatternAttribute(): ?string
    {
        return match ($this->type) {
            'route' => match ($this->route_name) {
                'catalog.index' => 'catalog.*',
                'domain.search' => 'domain.*',
                'domain-premium.index' => 'domain-premium.*',
                'announcements.index' => 'announcements.*',
                default => $this->route_name,
            },
            'page' => 'page.show',
            default => null,
        };
    }
}
