<?php

namespace App\Http\Middleware;

use App\Support\CspNonce;
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
            $reportUri = (string) config('security.csp_report_uri', '/csp-report');
            $headers['Content-Security-Policy-Report-Only'] = implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'self'",
                "form-action 'self' https:",
                "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://www.google.com https://www.gstatic.com https://www.googletagmanager.com",
                // Event handler inline sudah dimigrasi ke atribut data-* (partials/csp-actions);
                // CSP_ALLOW_INLINE_HANDLERS=true hanya untuk rollback darurat.
                'script-src-attr '.(config('security.csp_allow_inline_handlers', false) ? "'unsafe-inline'" : "'none'"),
                "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
                "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com",
                "img-src 'self' data: blob: https:",
                "connect-src 'self' https: wss:",
                "frame-src 'self' https:",
                'report-uri '.$reportUri,
            ]);
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
}
