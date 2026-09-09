<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu jenis berkas yang bisa diwajibkan untuk pendaftaran domain --
 * mis. "KTP Penanggung Jawab", "NIB", "Surat Permohonan".
 *
 * Menggantikan daftar hardcoded di DomainDocument::requirements(), yang
 * dulu memaksa deploy ulang setiap kali aturan registry berubah.
 */
class DocumentRequirement extends Model
{
    protected $fillable = ['name', 'description', 'is_required', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tldLinks(): HasMany
    {
        return $this->hasMany(DocumentRequirementTld::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DomainDocument::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Semua persyaratan yang berlaku untuk satu ekstensi domain.
     *
     * Dicocokkan ke ekstensi TERPANJANG yang cocok -- ".co.id" harus
     * mengambil persyaratan ".co.id", BUKAN ikut ".id", karena keduanya
     * bisa saja sama-sama terdaftar dengan syarat berbeda.
     */
    public static function forExtension(?string $extension): \Illuminate\Support\Collection
    {
        if (blank($extension)) {
            return collect();
        }

        $ext = '.' . ltrim(strtolower(trim($extension)), '.');

        return static::active()
            ->whereHas('tldLinks', fn ($q) => $q->where('extension', $ext))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Apakah ekstensi ini butuh berkas sama sekali? Dipakai gerbang
     * provisioning & checkout untuk memutuskan apakah domain ditahan.
     */
    public static function extensionNeedsDocuments(?string $extension): bool
    {
        if (blank($extension)) {
            return false;
        }

        $ext = '.' . ltrim(strtolower(trim($extension)), '.');

        return static::active()
            ->where('is_required', true)
            ->whereHas('tldLinks', fn ($q) => $q->where('extension', $ext))
            ->exists();
    }
}
