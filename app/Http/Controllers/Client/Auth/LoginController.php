<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LoginAttempt;
use App\Models\Setting;
use App\Services\Security\CaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request, CaptchaService $captcha): View
    {
        return view('client.auth.login', [
            'captcha' => $this->captchaData($request, $captcha),
        ]);
    }

    public function createBootstrap(Request $request, CaptchaService $captcha): View
    {
        return view('client.auth.login', [
            'captcha' => $this->captchaData($request, $captcha),
        ]);
    }

    /**
     * Data untuk partial CAPTCHA.
     */
    public static function buildCaptchaData(Request $request, CaptchaService $captcha): array
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

    private function captchaData(Request $request, CaptchaService $captcha): array
    {
        return self::buildCaptchaData($request, $captcha);
    }

    public function store(Request $request, CaptchaService $captcha): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'client-login|' . Str::lower($credentials['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            LoginAttempt::record('client', $credentials['email'], false, 'throttled', $request);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik."]);
        }

        if ($pesan = $captcha->verify($request)) {
            LoginAttempt::record('client', $credentials['email'], false, 'captcha_failed', $request);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['captcha' => $pesan]);
        }

        if (! Auth::guard('client')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            $reason = Client::where('email', $credentials['email'])->exists() ? 'wrong_password' : 'not_found';
            LoginAttempt::record('client', $credentials['email'], false, $reason, $request);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Email atau password salah.']);
        }

        $client = Auth::guard('client')->user();

        if (! $client->isActive()) {
            Auth::guard('client')->logout();

            LoginAttempt::record('client', $credentials['email'], false, 'inactive', $request);

            return back()->withErrors([
                'email' => 'Akun Anda sedang tidak aktif. Silakan hubungi tim support kami.',
            ]);
        }

        // Verifikasi email wajib (kalau diaktifkan admin). Ditaruh setelah
        // password benar, supaya halaman ini tidak bisa dipakai menebak
        // email mana yang terdaftar.
        if (Setting::get('require_email_verification', '1') === '1' && ! $client->email_verified_at) {
            Auth::guard('client')->logout();

            LoginAttempt::record('client', $credentials['email'], false, 'unverified', $request);

            $request->session()->put('verify.email', $client->email);

            return redirect()->route('client.verify.notice')
                ->with('error', 'Email Anda belum diverifikasi. Kami sudah mengirimkan kode verifikasi baru.');
        }

        // ── Jalur 2FA ── (sama pola dengan admin: logout dulu, belum
        // benar-benar masuk sampai kode OTP diverifikasi)
        if ($client->two_factor_enabled) {
            Auth::guard('client')->logout();

            $code = $client->generateOtp();

            try {
                $client->notify(new \App\Notifications\SendClientOtpCode($code));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Gagal mengirim OTP klien: ' . $e->getMessage(), ['client_id' => $client->id]);

                return back()->withErrors([
                    'email' => 'Kode verifikasi gagal dikirim. Silakan hubungi support kami.',
                ]);
            }

            $request->session()->put('otp.client_id', $client->id);
            $request->session()->put('otp.remember', $request->boolean('remember'));

            return redirect()->route('client.otp.challenge');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        LoginAttempt::record('client', $client->email, true, null, $request);

        $client->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()->intended(route('client.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('client')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('client.login');
    }
}
