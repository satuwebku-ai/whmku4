<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setara `auth:client` bawaan Laravel -- lihat komentar AdminMiddleware,
 * alasan & pola delegasinya sama persis.
 */
class ClientMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return app(Authenticate::class)->handle($request, $next, 'client');
    }
}
