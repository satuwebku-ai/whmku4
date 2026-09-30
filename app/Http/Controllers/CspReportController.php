<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menerima laporan CSP tanpa menyimpan URL query, token, atau sampel skrip.
 */
class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $content = $request->getContent();
        if (strlen($content) > 16_384) {
            return response()->noContent(413);
        }

        $payload = json_decode($content, true);
        if (! is_array($payload)) {
            return response()->noContent();
        }

        $reports = array_is_list($payload) ? array_slice($payload, 0, 5) : [$payload];

        foreach ($reports as $report) {
            if (! is_array($report)) {
                continue;
            }

            $body = $report['body'] ?? $report['csp-report'] ?? $report;
            if (! is_array($body)) {
                continue;
            }

            Log::notice('CSP violation report', [
                'document' => $this->safeLocation($body['document-uri'] ?? $body['documentURL'] ?? null),
                'blocked' => $this->safeLocation($body['blocked-uri'] ?? $body['blockedURL'] ?? null),
                'directive' => $this->safeText(
                    $body['effective-directive']
                        ?? $body['effectiveDirective']
                        ?? $body['violated-directive']
                        ?? null
                ),
                'source' => $this->safeLocation($body['source-file'] ?? $body['sourceFile'] ?? null),
                'line' => isset($body['line-number']) && is_numeric($body['line-number'])
                    ? (int) $body['line-number']
                    : null,
            ]);
        }

        return response()->noContent();
    }

    private function safeLocation(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $parts = parse_url($value);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (in_array($scheme, ['data', 'blob', 'javascript'], true)) {
            return $scheme . ':';
        }

        $location = ($scheme !== '' ? $scheme . '://' : '')
            . ($parts['host'] ?? '')
            . ($parts['path'] ?? '');

        return $this->safeText($location);
    }

    private function safeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';

        return substr($value, 0, 255);
    }
}