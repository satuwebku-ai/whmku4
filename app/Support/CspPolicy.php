<?php

namespace App\Support;

/**
 * Menyusun nilai header Content-Security-Policy.
 *
 * Host pihak ketiga hanya masuk kebijakan kalau integrasinya benar-benar
 * aktif di Setting (GA/GTM, Facebook Pixel, livechat), sehingga `connect-src`
 * dan `img-src` tidak perlu lagi membuka `https:` untuk semua host.
 *
 * Kelas ini sengaja tidak bergantung pada Laravel supaya mudah diuji.
 */
final class CspPolicy
{
    /** Host skrip yang selalu dipakai layout (CDN aset + reCAPTCHA). */
    private const SCRIPT_BASE = [
        'https://cdn.jsdelivr.net',
        'https://cdnjs.cloudflare.com',
        'https://www.google.com',
        'https://www.gstatic.com',
    ];

    // cdn.jsdelivr.net: Bootstrap Icons (CSS + file font) di tema namahost.
    private const STYLE_BASE = [
        'https://cdn.jsdelivr.net',
        'https://cdnjs.cloudflare.com',
        'https://fonts.googleapis.com',
    ];

    private const FONT_BASE = [
        'data:',
        'https://cdn.jsdelivr.net',
        'https://cdnjs.cloudflare.com',
        'https://fonts.gstatic.com',
    ];

    /**
     * Gambar yang dipakai kode aplikasi. Avatar inisial berupa SVG data: lokal
     * (App\Support\InitialsAvatar), jadi tidak ada host pihak ketiga.
     */
    private const IMG_BASE = [
        'data:',
        'blob:',
    ];

    /**
     * Host per integrasi, mengikuti dokumentasi vendor masing-masing.
     * Daftar ini perlu dikonfirmasi lewat laporan pelanggaran di staging.
     */
    private const GOOGLE_TAG = [
        'script'  => ['https://www.googletagmanager.com'],
        'connect' => ['https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://*.googletagmanager.com'],
        'img'     => ['https://*.google-analytics.com', 'https://*.googletagmanager.com'],
    ];

    private const FB_PIXEL = [
        'script'  => ['https://connect.facebook.net'],
        'connect' => ['https://www.facebook.com', 'https://connect.facebook.net'],
        'img'     => ['https://www.facebook.com'],
    ];

    private const TAWKTO = [
        'script'  => ['https://embed.tawk.to', 'https://*.tawk.to'],
        'style'   => ['https://*.tawk.to'],
        'font'    => ['https://*.tawk.to'],
        'connect' => ['https://*.tawk.to', 'wss://*.tawk.to'],
        'img'     => ['https://*.tawk.to', 'https://tawk.link'],
    ];

    private const CRISP = [
        'script'  => ['https://client.crisp.chat'],
        'style'   => ['https://client.crisp.chat'],
        'font'    => ['https://client.crisp.chat'],
        'connect' => ['https://*.crisp.chat', 'wss://*.crisp.chat'],
        'img'     => ['https://*.crisp.chat'],
    ];

    /**
     * @param array{ga?: bool, gtm?: bool, fb_pixel?: bool, livechat?: string|null} $integrations
     * @param array{
     *     report_uri?: string,
     *     allow_inline_handlers?: bool,
     *     img_allow_any_https?: bool,
     *     extra_img?: string|array<int, string>|null,
     *     extra_connect?: string|array<int, string>|null,
     * } $options
     */
    public static function build(string $nonce, array $integrations = [], array $options = []): string
    {
        $googleTag = ! empty($integrations['ga']) || ! empty($integrations['gtm']);
        $fbPixel   = ! empty($integrations['fb_pixel']);
        $livechat  = $integrations['livechat'] ?? null;

        $parts = [];
        if ($googleTag) {
            $parts[] = self::GOOGLE_TAG;
        }
        if ($fbPixel) {
            $parts[] = self::FB_PIXEL;
        }
        if ($livechat === 'tawkto') {
            $parts[] = self::TAWKTO;
        } elseif ($livechat === 'crisp') {
            $parts[] = self::CRISP;
        }

        $script  = self::collect($parts, 'script', self::SCRIPT_BASE);
        $style   = self::collect($parts, 'style', self::STYLE_BASE);
        $font    = self::collect($parts, 'font', self::FONT_BASE);
        $connect = self::collect($parts, 'connect', [], self::hosts($options['extra_connect'] ?? null));
        $img     = self::collect($parts, 'img', self::IMG_BASE, self::hosts($options['extra_img'] ?? null));

        if (! empty($options['img_allow_any_https'])) {
            $img[] = 'https:';
        }

        $inlineHandlers = ! empty($options['allow_inline_handlers']) ? "'unsafe-inline'" : "'none'";

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            // Pembayaran memakai POST lalu redirect ke halaman gateway (host dinamis);
            // Chrome menerapkan form-action juga pada redirect, jadi tidak diketatkan.
            "form-action 'self' https:",
            "script-src 'self' 'nonce-{$nonce}' ".implode(' ', $script),
            'script-src-attr '.$inlineHandlers,
            "style-src 'self' 'unsafe-inline' ".implode(' ', $style),
            "font-src 'self' ".implode(' ', $font),
            "img-src 'self' ".implode(' ', array_unique($img)),
            "connect-src 'self'".($connect === [] ? '' : ' '.implode(' ', $connect)),
            "frame-src 'self' https:",
            'report-uri '.($options['report_uri'] ?? '/csp-report'),
        ]);
    }

    /**
     * Gabungkan host dasar + host tiap integrasi aktif + host tambahan, tanpa duplikat.
     *
     * @param list<array<string, list<string>>> $parts
     * @param list<string> $base
     * @param list<string> $extra
     * @return list<string>
     */
    private static function collect(array $parts, string $directive, array $base, array $extra = []): array
    {
        $list = $base;
        foreach ($parts as $part) {
            array_push($list, ...($part[$directive] ?? []));
        }
        array_push($list, ...$extra);

        return array_values(array_unique($list));
    }

    /**
     * Parse daftar host dari env (dipisah koma/spasi). Entri yang bukan origin
     * sederhana dibuang, sehingga env tidak bisa menyisipkan direktif lain
     * (titik koma, kutip, baris baru).
     *
     * @param string|array<int, string>|null $value
     * @return list<string>
     */
    public static function hosts(string|array|null $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        $items = is_array($value) ? $value : preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY);

        $valid = [];
        foreach ($items ?: [] as $item) {
            $item = trim((string) $item);
            if (preg_match('#^(https?|wss?)://(\*\.)?[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+(:\d{1,5})?$#', $item)) {
                $valid[] = $item;
            }
        }

        return $valid;
    }
}
