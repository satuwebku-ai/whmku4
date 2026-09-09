<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'category',
        'meta_title', 'meta_description', 'is_published', 'is_pinned', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Announcement $item) {
            if (blank($item->slug)) {
                $item->slug = static::uniqueSlug($item->title, $item->id);
            } else {
                $item->slug = Str::slug($item->slug);
            }

            if ($item->is_published && blank($item->published_at)) {
                $item->published_at = now();
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'pengumuman';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Hanya yang sudah terbit dan waktunya sudah tiba —
     * mendukung penjadwalan pengumuman.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function getSeoTitleAttribute(): string
    {
        return (string) ($this->meta_title ?: $this->title ?: '');
    }

    public function getSeoDescriptionAttribute(): string
    {
        return (string) ($this->meta_description
            ?: ($this->excerpt ?: Str::limit(strip_tags((string) $this->content), 155)));
    }

    public function getCategoryBadgeAttribute(): string
    {
        return match ($this->category) {
            'maintenance' => 'pending',
            'incident' => 'suspended',
            'promo' => 'active',
            default => 'inactive',
        };
    }
}
