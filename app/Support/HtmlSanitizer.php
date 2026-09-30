<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Pembersih HTML berbasis allowlist untuk konten yang ditulis admin dan
 * ditampilkan apa adanya (Pengumuman, Halaman CMS).
 *
 * Tujuannya menutup stored-XSS: admin dengan hak konten terbatas tidak bisa
 * menyisipkan <script>, atribut on*, javascript:, dll. yang lalu berjalan di
 * browser pengunjung/klien/superadmin pada domain yang sama. Format tulisan
 * biasa (judul, paragraf, daftar, tabel, tautan, gambar, embed video) tetap
 * lolos. Dipakai saat RENDER, jadi data lama tidak perlu diubah.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'sub', 'sup', 'small', 'mark',
        'blockquote', 'pre', 'code', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
        'a', 'img', 'figure', 'figcaption', 'iframe',
    ];

    /** Dibuang beserta isinya (bukan sekadar dibuka bungkusnya). */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe_unsafe', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'link', 'meta', 'base', 'svg', 'math', 'noscript', 'template', 'frame', 'frameset',
    ];

    private const GLOBAL_ATTRS = ['class', 'title', 'style', 'align', 'dir', 'lang'];

    private const TAG_ATTRS = [
        'a' => ['href', 'target', 'rel', 'name'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'td' => ['colspan', 'rowspan', 'width', 'height'],
        'th' => ['colspan', 'rowspan', 'width', 'height', 'scope'],
        'col' => ['span', 'width'],
        'colgroup' => ['span'],
        'ol' => ['start', 'type'],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen', 'frameborder', 'loading'],
    ];

    /** Embed hanya dari layanan yang umum dipakai. */
    private const IFRAME_HOSTS = [
        'www.youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com', 'www.google.com',
    ];

    public static function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="__sanitize_root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__sanitize_root');

        if (! $root) {
            return e($html);
        }

        self::sanitizeChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        // Salin dulu: daftar node berubah selama kita menghapus/membuka bungkus.
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
                $parent->removeChild($node);
                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);
                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Tag tak dikenal: buang tag-nya, pertahankan isinya (setelah dibersihkan).
                self::sanitizeChildren($node);
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            if ($tag === 'iframe' && ! self::iframeAllowed($node)) {
                $parent->removeChild($node);
                continue;
            }

            self::sanitizeAttributes($node, $tag);
            self::sanitizeChildren($node);
        }
    }

    private static function sanitizeAttributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRS, self::TAG_ATTRS[$tag] ?? []);

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = $attr->value;

            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);
                continue;
            }

            if ($name === 'href' && ! self::safeUrl($value, ['http', 'https', 'mailto', 'tel'])) {
                $el->removeAttribute($attr->name);
            } elseif ($name === 'src' && $tag === 'img' && ! self::safeUrl($value, ['http', 'https'], true)) {
                $el->removeAttribute($attr->name);
            } elseif ($name === 'style') {
                $clean = self::cleanStyle($value);
                $clean === '' ? $el->removeAttribute($attr->name) : $el->setAttribute('style', $clean);
            } elseif ($name === 'target' && ! in_array(strtolower($value), ['_blank', '_self'], true)) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a' && strtolower($el->getAttribute('target')) === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function iframeAllowed(DOMElement $el): bool
    {
        $src = trim($el->getAttribute('src'));

        if (! preg_match('#^https://#i', $src)) {
            return false;
        }

        $host = strtolower((string) parse_url($src, PHP_URL_HOST));
        $path = (string) parse_url($src, PHP_URL_PATH);

        if (! in_array($host, self::IFRAME_HOSTS, true)) {
            return false;
        }

        // www.google.com hanya untuk embed peta, bukan halaman Google lain.
        return $host !== 'www.google.com' || str_starts_with($path, '/maps/embed');
    }

    /**
     * @param list<string> $schemes
     */
    private static function safeUrl(string $url, array $schemes, bool $allowDataImage = false): bool
    {
        // Browser mengabaikan spasi/kontrol di dalam skema ("java\tscript:").
        $normalized = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5)) ?? '');

        if ($normalized === '') {
            return false;
        }

        if ($allowDataImage && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$#', $normalized)) {
            return true;
        }

        if (preg_match('#^([a-z][a-z0-9+.\-]*):#', $normalized, $m)) {
            return in_array($m[1], $schemes, true);
        }

        // Relatif (/path, ./x, ?q, #anchor, nama-file) — aman.
        return true;
    }

    private static function cleanStyle(string $style): string
    {
        $keep = [];

        foreach (explode(';', $style) as $decl) {
            $decl = trim($decl);

            if ($decl === '' || ! str_contains($decl, ':')) {
                continue;
            }

            $probe = strtolower(preg_replace('/[\s\\\\]+/', '', $decl) ?? '');

            if (preg_match('/(expression\(|url\(|javascript:|vbscript:|behavior:|-moz-binding|@import|position:(fixed|absolute|sticky)|z-index)/', $probe)) {
                continue;
            }

            $keep[] = $decl;
        }

        return implode('; ', $keep);
    }
}
