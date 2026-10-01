<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Template balasan cepat (teks support/helpdesk) yang bisa dipilih admin
 * saat membalas email atau live chat. Template bertanda use_for_ai juga
 * dibaca bot AI sebagai panduan jawaban.
 */
class MailTemplate extends Model
{
    protected $fillable = ['title', 'category', 'subject', 'body', 'is_active', 'use_for_ai', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'use_for_ai' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }
}
