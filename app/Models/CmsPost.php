<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsPost extends Model
{
    use UsesCmsContentTable;

    public const CONTENT_TYPE = 'post';

    protected $fillable = [
        'cms_category_id', 'admin_id', 'title', 'slug', 'excerpt',
        'content', 'cover_image', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CmsCategory::class, 'cms_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $query) => $query
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    public function getSeoTitleAttribute(): string
    {
        return (string) ($this->title ?: '');
    }

    public function getSeoDescriptionAttribute(): string
    {
        return (string) ($this->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $this->content), 155));
    }
}
