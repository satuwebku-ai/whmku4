<?php

namespace App\Support;

/**
 * Avatar inisial buatan sendiri (SVG data URI), pengganti layanan pihak ketiga.
 * Nama pengguna tidak pernah keluar dari server, dan data: sudah diizinkan img-src.
 */
final class InitialsAvatar
{
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $first  = isset($words[0]) ? mb_substr($words[0], 0, 1) : '';
        $second = isset($words[1]) ? mb_substr($words[1], 0, 1) : '';
        $text   = mb_strtoupper($first . $second);

        // Hanya huruf/angka yang dipakai, supaya aman ditanam ke SVG.
        $text = preg_replace('/[^\p{L}\p{N}]/u', '', $text) ?? '';

        return $text !== '' ? $text : 'NA';
    }

    public static function dataUri(string $name, string $background = '#6366F1', string $color = '#FFFFFF'): string
    {
        $text = htmlspecialchars(self::initials($name), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="128" height="128">'
            . '<rect width="128" height="128" fill="' . $background . '"/>'
            . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="' . $color . '"'
            . ' font-family="Arial, Helvetica, sans-serif" font-size="52" font-weight="600">' . $text . '</text>'
            . '</svg>';

        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
    }
}
