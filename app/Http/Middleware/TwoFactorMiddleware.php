<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2FA sudah ditegakkan di alur login itu sendiri: Auth\Admin\LoginController
 * TIDAK memanggil Auth::login() sama sekali untuk admin ber-2FA sebelum
 * Auth\Admin\OtpController::verify() berhasil -- jadi tidak ada sesi
 * "setengah login" yang bisa dipakai lolos dari OTP lewat jalur normal.
 *
 * Middleware ini pertahanan LAPIS KEDUA untuk route sensitif (mis. ganti
 * password/email superadmin lain), berjaga-jaga kalau di masa depan ada
 * kode baru yang keliru memanggil Auth::guard('admin')->login() langsung
 * tanpa lewat OtpController. OtpController::verify() menandai
 * session('otp_verified_at') setiap kali OTP berhasil diverifikasi;
 * middleware ini menolak akses kalau admin ber-2FA tapi penanda itu tidak
 * ada di sesi berjalan.
 *
 * Belum dipasang di route manapun secara default -- pasang manual di
 * route yang dianggap perlu proteksi ekstra dengan alias `2fa`.
 */
class TwoFactorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && $admin->two_factor_enabled && ! $request->session()->get('otp_verified_at')) {
            Auth::guard('admin')->logout();

            return redirect()->route('admin.login')
                ->withErrors(['username' => 'Verifikasi 2FA diperlukan untuk mengakses halaman ini. Silakan login ulang.']);
        }

        return $next($request);
    }
}
