<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        // Local development and the test suite deliberately use HTTP.
        if (app()->environment('local', 'testing') || $request->is('up')) {
            return $next($request);
        }

        if (! $request->isSecure()) {
            // Preserve the requested host and path instead of relying on
            // APP_URL, which may point to a different deployment.
            $secureUrl = preg_replace(
                '#^http://#i',
                'https://',
                $request->getUri()
            );

            return redirect()->to($secureUrl, 308);
        }

        return $next($request);
    }
}