<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses berdasarkan peran admin.
 *
 * Dipakai sebagai `role:superadmin` atau `role:superadmin,admin` di route.
 * Tanpa ini kolom `role` hanya jadi label kosong — semua admin bisa
 * melakukan apa saja, termasuk menghapus data dan mengubah pengaturan
 * pembayaran.
 *
 * Sebelumnya bernama EnsureAdminRole -- diganti nama mengikuti daftar
 * middleware final Master Blueprint (RoleMiddleware). Isinya identik,
 * alias route `role:` di bootstrap/app.php ikut diarahkan ke sini
 * sehingga tidak ada satupun baris di routes/admin.php yang perlu diubah.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        // Superadmin selalu lolos: tanpa pengecualian ini, salah setel
        // sekali saja bisa mengunci pemilik dari panelnya sendiri.
        if ($admin->role === 'superadmin') {
            return $next($request);
        }

        if (! in_array($admin->role, $roles, true)) {
            abort(403, 'Akun Anda tidak punya izin untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}
