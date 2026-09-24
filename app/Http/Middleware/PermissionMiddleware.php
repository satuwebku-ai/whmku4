<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware final Master Blueprint untuk otorisasi berbasis permission
 * (tabel roles/permissions), dipakai sebagai `permission:billing` atau
 * `permission:billing,sales` (lolos kalau punya salah satu).
 *
 * Route yang SUDAH ADA di routes/admin.php tetap memakai `module:xxx`
 * (EnsureAdminModule, membaca kolom lama `admins.permissions`) supaya
 * tidak ada yang berisiko berubah perilakunya. Middleware ini untuk route
 * BARU yang ingin memakai RBAC berbasis tabel secara langsung.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        foreach ($permissions as $permission) {
            if ($admin->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'Akun Anda tidak punya izin untuk mengakses halaman ini.');
    }
}
