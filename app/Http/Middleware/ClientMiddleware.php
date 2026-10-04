<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setara `auth:client` bawaan Laravel -- lihat komentar AdminMiddleware,
 * alasan & pola delegasinya sama persis.
 *
 * Ditambah: sesi dibatalkan kalau password klien sudah berganti sejak sesi
 * itu dimulai (mis. diganti dari perangkat lain), supaya ganti password
 * benar-benar mengeluarkan sesi lama. Sesi yang dimulai sebelum fitur ini
 * ada cukup "dicap" sekali pada permintaan berikutnya.
 */
class ClientMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return app(Authenticate::class)->handle($request, function (Request $request) use ($next) {
            $client = $request->user('client');

            if ($client && $request->hasSession()) {
                $current = (string) $client->getAuthPassword();
                $stamp = $request->session()->get('client_password_stamp');

                // Cap berisi id klien + hash password. Cap milik klien lain
                // (sesi dipakai bergantian) cukup diganti, bukan dianggap
                // sebagai password yang berubah.
                if (! is_array($stamp) || ($stamp['id'] ?? null) !== $client->getAuthIdentifier()) {
                    $request->session()->put('client_password_stamp', ['id' => $client->getAuthIdentifier(), 'hash' => $current]);
                } elseif (! hash_equals((string) $stamp['hash'], $current)) {
                    Auth::guard('client')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('client.login')
                        ->with('error', 'Password akun diganti. Silakan masuk kembali.');
                }
            }

            return $next($request);
        }, 'client');
    }
}
