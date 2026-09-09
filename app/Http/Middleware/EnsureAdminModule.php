<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses berdasarkan modul yang diizinkan admin ini.
 *
 * Dipakai sebagai `module:billing` atau `module:sales,billing` (lolos
 * kalau admin punya salah satu) di route. Modul & default per peran
 * didefinisikan di App\Models\Admin::MODULES / ROLE_DEFAULT_MODULES.
 * Superadmin selalu lolos lewat Admin::hasModule() -- tanpa itu, sekali
 * salah setel bisa mengunci pemilik dari panelnya sendiri.
 */
class EnsureAdminModule
{
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        foreach ($modules as $module) {
            if ($admin->hasModule($module)) {
                return $next($request);
            }
        }

        abort(403, 'Akun Anda belum diberi akses ke modul ini. Hubungi superadmin untuk membuka akses lewat Admin & Akses.');
    }
}
