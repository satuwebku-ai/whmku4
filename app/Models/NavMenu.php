<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route as RouteFacade;

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
     */
    public const BUILTIN_ROUTES = [
        'home' => 'Beranda',
        'catalog.index' => 'Katalog Hosting',
        'domain.search' => 'Cek Domain',
        'announcements.index' => 'Pengumuman',
        'cart.index' => 'Keranjang',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
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
            'route' => ($this->route_name && RouteFacade::has($this->route_name))
                ? route($this->route_name)
                : null,

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
                'announcements.index' => 'announcements.*',
                default => $this->route_name,
            },
            'page' => 'page.show',
            default => null,
        };
    }
}
