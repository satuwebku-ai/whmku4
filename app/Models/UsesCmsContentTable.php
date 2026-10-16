<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared storage for CMS pages, blog posts, and blog categories.
 *
 * Each public model defines CONTENT_TYPE so callers keep using the same
 * familiar model APIs while reads and writes stay isolated by content type.
 */
trait UsesCmsContentTable
{
    /**
     * Semua model CMS memakai satu tabel, jadi nama tabel ditetapkan di sini
     * -- model tidak perlu mendeklarasikan $table sendiri.
     */
    public function initializeUsesCmsContentTable(): void
    {
        $this->setTable('cms_contents');
    }

    /**
     * Isi konten yang aman ditampilkan apa adanya ({!! !!}) -- HTML dibersihkan
     * lewat allowlist supaya tidak bisa menyisipkan script (stored XSS).
     */
    public function getSafeContentAttribute(): string
    {
        return \App\Support\HtmlSanitizer::clean($this->content);
    }

    protected static function bootUsesCmsContentTable(): void
    {
        $contentType = static::CONTENT_TYPE;

        static::addGlobalScope(
            'cms_content_type_'.$contentType,
            static function (Builder $query) use ($contentType): void {
                $query->where($query->getModel()->qualifyColumn('type'), $contentType);
            }
        );

        static::creating(static function (Model $model) use ($contentType): void {
            $model->setAttribute('type', $contentType);
        });
    }
}
