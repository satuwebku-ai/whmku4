<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Notifications\SendClientOtpCode;
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
        $client = $this->pendingClient($request);

        if (! $client) {
            return redirect()->route('client.login')
                ->withErrors(['email' => 'Sesi verifikasi berakhir. Silakan login ulang.']);
        }

        return view('client.auth.otp', [
            'maskedEmail' => $this->maskEmail($client->email),
            'expiresAt' => $client->otp_expires_at,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $client = $this->pendingClient($request);

        if (! $client) {
            return redirect()->route('client.login')
                ->withErrors(['email' => 'Sesi verifikasi berakhir. Silakan login ulang.']);
        }

        if ($client->otp_attempts >= self::MAX_ATTEMPTS) {
            $client->clearOtp();
            $request->session()->forget('otp');

            return redirect()->route('client.login')
                ->withErrors(['email' => 'Terlalu banyak percobaan kode. Silakan login ulang untuk meminta kode baru.']);
        }

        if (! $client->otpIsValid($data['code'])) {
            $client->increment('otp_attempts');

            $remaining = self::MAX_ATTEMPTS - $client->otp_attempts;

            return back()->withErrors([
                'code' => $client->otp_expires_at?->isPast()
                    ? 'Kode sudah kedaluwarsa. Klik "Kirim ulang kode" untuk meminta yang baru.'
                    : "Kode salah. Sisa percobaan: {$remaining}.",
            ]);
        }

        $remember = (bool) $request->session()->get('otp.remember', false);

        $client->clearOtp();
        $request->session()->forget('otp');

        Auth::guard('client')->login($client, $remember);
        $request->session()->regenerate();

        $client->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()->intended(route('client.dashboard'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $client = $this->pendingClient($request);

        if (! $client) {
            return redirect()->route('client.login');
        }

        $key = 'client-otp-resend|' . $client->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors([
                'code' => 'Terlalu sering meminta kode. Tunggu ' . RateLimiter::availableIn($key) . ' detik.',
            ]);
        }

        RateLimiter::hit($key, 300);

        try {
            $client->notify(new SendClientOtpCode($client->generateOtp()));
        } catch (Throwable $e) {
            Log::error('Gagal mengirim ulang OTP klien: ' . $e->getMessage(), ['client_id' => $client->id]);

            return back()->withErrors(['code' => 'Kode gagal dikirim. Periksa konfigurasi email server, atau hubungi support.']);
        }

        return back()->with('success', 'Kode baru sudah dikirim ke email Anda.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $client = $this->pendingClient($request);
        $client?->clearOtp();

        $request->session()->forget('otp');

        return redirect()->route('client.login');
    }

    private function pendingClient(Request $request): ?Client
    {
        $id = $request->session()->get('otp.client_id');

        return $id ? Client::find($id) : null;
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($name, 0, 2);

        return $visible . str_repeat('*', max(mb_strlen($name) - 2, 1)) . '@' . $domain;
    }
}
