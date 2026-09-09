<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Notifications\SendOtpCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

class OtpController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function challenge(Request $request): View|RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if (! $admin) {
            return redirect()->route('admin.login')
                ->withErrors(['username' => 'Sesi verifikasi berakhir. Silakan login ulang.']);
        }

        return view('admin.auth.otp', [
            'maskedEmail' => $this->maskEmail($admin->email),
            'expiresAt' => $admin->otp_expires_at,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $admin = $this->pendingAdmin($request);

        if (! $admin) {
            return redirect()->route('admin.login')
                ->withErrors(['username' => 'Sesi verifikasi berakhir. Silakan login ulang.']);
        }

        // Batasi tebakan kode supaya OTP 6 digit tidak bisa di-brute force.
        if ($admin->otp_attempts >= self::MAX_ATTEMPTS) {
            $admin->clearOtp();
            $request->session()->forget('otp');

            return redirect()->route('admin.login')
                ->withErrors(['username' => 'Terlalu banyak percobaan kode. Silakan login ulang untuk meminta kode baru.']);
        }

        if (! $admin->otpIsValid($data['code'])) {
            $admin->increment('otp_attempts');

            $remaining = self::MAX_ATTEMPTS - $admin->otp_attempts;

            return back()->withErrors([
                'code' => $admin->otp_expires_at?->isPast()
                    ? 'Kode sudah kedaluwarsa. Klik "Kirim ulang kode" untuk meminta yang baru.'
                    : "Kode salah. Sisa percobaan: {$remaining}.",
            ]);
        }

        // Kode benar — baru sekarang sesi login dibuat.
        $remember = (bool) $request->session()->get('otp.remember', false);

        $admin->clearOtp();
        $request->session()->forget('otp');

        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $key = 'otp-resend|' . $admin->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors([
                'code' => 'Terlalu sering meminta kode. Tunggu ' . RateLimiter::availableIn($key) . ' detik.',
            ]);
        }

        RateLimiter::hit($key, 300);

        try {
            $admin->notify(new SendOtpCode($admin->generateOtp()));
        } catch (Throwable $e) {
            Log::error('Gagal mengirim ulang OTP: ' . $e->getMessage(), ['admin_id' => $admin->id]);

            return back()->withErrors(['code' => 'Kode gagal dikirim. Periksa konfigurasi email server.']);
        }

        return back()->with('success', 'Kode baru sudah dikirim ke email Anda.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);
        $admin?->clearOtp();

        $request->session()->forget('otp');

        return redirect()->route('admin.login');
    }

    private function pendingAdmin(Request $request): ?Admin
    {
        $id = $request->session()->get('otp.admin_id');

        return $id ? Admin::find($id) : null;
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($name, 0, 2);

        return $visible . str_repeat('*', max(mb_strlen($name) - 2, 1)) . '@' . $domain;
    }
}
