<?php

namespace App\Support;

use Illuminate\Support\Str;

class UniqueSlug
{
    /**
     * Return a URL-safe slug that is not already taken by the supplied query.
     */
    public static function make(string $value, string $fallback, callable $exists): string
    {
        $base = Str::slug($value) ?: $fallback;
        $slug = $base;
        $suffix = 2;

        while ($exists($slug)) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
