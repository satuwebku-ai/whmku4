<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setara `auth:admin` bawaan Laravel, dinamai ulang sebagai kelas sendiri
 * karena Master Blueprint mendaftarkan "AdminMiddleware" sebagai bagian
 * dari middleware final. Isinya DELEGASI LANGSUNG ke middleware bawaan
 * Illuminate\Auth\Middleware\Authenticate dengan guard 'admin' -- bukan
 * ditulis ulang dari nol -- supaya perilakunya 100% identik dengan
 * `auth:admin` yang sudah dipakai (redirect-ke-login, integrasi dengan
 * Auth facade, dsb).
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return app(Authenticate::class)->handle($request, $next, 'admin');
    }
}
