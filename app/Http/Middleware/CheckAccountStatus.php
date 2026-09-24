<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selama ini `is_active` cuma dicek SEKALI, saat proses login
 * (Auth\Admin\LoginController::store()) -- kalau superadmin menonaktifkan
 * admin lain yang SEDANG login, sesi admin itu tetap jalan terus sampai ia
 * logout sendiri. Middleware ini menutup celah itu: dicek di setiap
 * request untuk guard admin.
 *
 * Guard client SENGAJA tidak ikut dicek -- model Client tidak punya
 * konsep `is_active` sama sekali (tidak ada kolomnya), jadi tidak ada
 * yang perlu ditutup di sisi itu.
 *
 * Belum dipasang otomatis di grup route manapun -- lihat catatan di
 * routes/admin.php untuk cara mengaktifkannya.
 */
class CheckAccountStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && ! $admin->is_active) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['username' => 'Akun admin ini sudah dinonaktifkan.']);
        }

        return $next($request);
    }
}
