<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\LoginAttempt;
use App\Services\Security\CaptchaService;
use App\Notifications\SendOtpCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class LoginController extends Controller
{
    public function create(Request $request, CaptchaService $captcha): View
    {
        return view('admin.auth.login', [
            'captcha' => $this->captchaData($request, $captcha),
        ]);
    }

    /**
     * Data yang dibutuhkan partial CAPTCHA.
     */
    private function captchaData(Request $request, CaptchaService $captcha): array
    {
        $required = $captcha->required($request);

        return [
            'required'  => $required,
            'recaptcha' => $captcha->usesRecaptcha(),
            'site_key'  => $captcha->siteKey(),
            'question'  => $required && ! $captcha->usesRecaptcha()
                ? $captcha->makeChallenge($request)['question']
                : null,
        ];
    }

    public function store(Request $request, CaptchaService $captcha): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($credentials['username']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            LoginAttempt::record('admin', $credentials['username'], false, 'throttled', $request);

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik."]);
        }

        // CAPTCHA diperiksa SEBELUM password, supaya bot tidak bisa memakai
        // form ini untuk menguji daftar password sama sekali.
        if ($pesan = $captcha->verify($request)) {
            LoginAttempt::record('admin', $credentials['username'], false, 'captcha_failed', $request);

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['captcha' => $pesan]);
        }

        // Validasi kredensial tanpa langsung membuat sesi login, supaya
        // akun ber-2FA belum dianggap masuk sebelum OTP diverifikasi.
        if (! Auth::guard('admin')->validate($credentials)) {
            RateLimiter::hit($throttleKey, 60);

            // Alasan dibedakan di CATATAN saja, tidak di pesan ke pengguna —
            // memberi tahu "username tidak ada" akan membantu penyerang
            // memetakan akun mana yang benar-benar ada.
            $reason = Admin::where('username', $credentials['username'])->exists()
                ? 'wrong_password'
                : 'not_found';

            LoginAttempt::record('admin', $credentials['username'], false, $reason, $request);

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Username atau password salah.']);
        }

        RateLimiter::clear($throttleKey);

        $admin = Admin::where('username', $credentials['username'])->firstOrFail();

        if (! $admin->is_active) {
            LoginAttempt::record('admin', $credentials['username'], false, 'inactive', $request);

            return back()->withErrors([
                'username' => 'Akun admin ini sedang dinonaktifkan. Hubungi superadmin.',
            ]);
        }

        // ── Jalur 2FA ──
        if ($admin->two_factor_enabled) {
            $code = $admin->generateOtp();

            try {
                $admin->notify(new SendOtpCode($code));
            } catch (Throwable $e) {
                Log::error('Gagal mengirim OTP: ' . $e->getMessage(), ['admin_id' => $admin->id]);

                return back()->withErrors([
                    'username' => 'Kode verifikasi gagal dikirim. Periksa konfigurasi email server, atau hubungi superadmin.',
                ]);
            }

            // Simpan hanya ID di session — belum login sampai OTP benar.
            $request->session()->put('otp.admin_id', $admin->id);
            $request->session()->put('otp.remember', $request->boolean('remember'));

            return redirect()->route('admin.otp.challenge');
        }

        // ── Jalur normal tanpa 2FA ──
        Auth::guard('admin')->login($admin, $request->boolean('remember'));
        $request->session()->regenerate();

        LoginAttempt::record('admin', $admin->username, true, null, $request);

        $this->recordLogin($admin, $request);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function recordLogin(Admin $admin, Request $request): void
    {
        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
    }
}
