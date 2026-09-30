<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\CspNonce;
use App\Support\CspPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk semua respons.
 *
 * CSP dimulai dalam mode Report-Only supaya sumber yang masih dipakai aplikasi
 * dapat diinventarisasi sebelum kebijakan diberlakukan dan berisiko memutus UI.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = app(CspNonce::class)->value();

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            // Cegah halaman (login, pembayaran) dibingkai situs lain (clickjacking).
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        if (config('security.csp_report_only', true)) {
            $headers['Content-Security-Policy-Report-Only'] = CspPolicy::build(
                $nonce,
                $this->activeIntegrations(),
                [
                    'report_uri'            => (string) config('security.csp_report_uri', '/csp-report'),
                    // Event handler inline sudah dimigrasi ke atribut data-* (partials/csp-actions);
                    // CSP_ALLOW_INLINE_HANDLERS=true hanya untuk rollback darurat.
                    'allow_inline_handlers' => (bool) config('security.csp_allow_inline_handlers', false),
                    // Konten CMS boleh memuat <img> https dari host mana pun (HtmlSanitizer).
                    // Default: hanya host yang dikenal + CSP_EXTRA_IMG_SRC.
                    'img_allow_any_https'   => (bool) config('security.csp_img_allow_any_https', false),
                    'extra_img'             => config('security.csp_extra_img_src'),
                    'extra_connect'         => config('security.csp_extra_connect_src'),
                ],
            );
        }

        // HSTS hanya bermakna (dan hanya dikirim) lewat HTTPS.
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Integrasi pihak ketiga yang sedang aktif, dibaca dengan syarat yang sama
     * seperti di view (head.blade.php, livechat.blade.php). Setting di-cache,
     * jadi murah; kalau database belum siap (instalasi awal) hasilnya kosong.
     *
     * @return array{ga: bool, gtm: bool, fb_pixel: bool, livechat: string|null}
     */
    private function activeIntegrations(): array
    {
        try {
            $provider = (string) Setting::get('livechat_provider', 'none');
            $hasChatId = filled(Setting::get('livechat_property_id'));

            return [
                'ga'       => filled(Setting::get('ga_measurement_id')),
                'gtm'      => filled(Setting::get('gtm_container_id')),
                'fb_pixel' => filled(Setting::get('fb_pixel_id')),
                'livechat' => $hasChatId && in_array($provider, ['tawkto', 'crisp'], true) ? $provider : null,
            ];
        } catch (\Throwable) {
            return ['ga' => false, 'gtm' => false, 'fb_pixel' => false, 'livechat' => null];
        }
    }
}
