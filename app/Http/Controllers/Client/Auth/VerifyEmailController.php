<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Notifications\ClientWelcome;
use App\Notifications\VerifyEmailCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

/**
 * Verifikasi alamat email pendaftar.
 *
 * Tujuannya memastikan email yang didaftarkan benar-benar dimiliki orang
 * itu — bukan alamat asal ketik. Ini penting karena seluruh komunikasi
 * penting (invoice, reset password, pemberitahuan layanan) dikirim ke sana.
 * Akun dengan email palsu berarti pelanggan yang tidak bisa dihubungi saat
 * tagihan jatuh tempo atau layanannya bermasalah.
 */
class VerifyEmailController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function notice(Request $request): View|RedirectResponse
    {
        $client = $this->pendingClient($request);

        if (! $client) {
            return redirect()->route('client.login');
        }

        if ($client->email_verified_at) {
            return redirect()->route('client.login')->with('success', 'Email Anda sudah terverifikasi. Silakan masuk.');
        }

        // Kirimkan kode kalau belum ada atau sudah kedaluwarsa.
        if (! $client->reset_code_hash || $client->reset_code_expires_at?->isPast()) {
            $this->sendCode($client);
        }

        return view('client.auth.verify-email', [
            'email' => $client->email,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $client = $this->pendingClient($request);

        if (! $client) {
            return redirect()->route('client.login');
        }

        if ($client->reset_attempts >= self::MAX_ATTEMPTS) {
            $client->clearResetCode();

            return back()->withErrors(['code' => 'Terlalu banyak percobaan. Klik "Kirim ulang kode" untuk meminta kode baru.']);
        }

        if (! $client->resetCodeIsValid($data['code'])) {
            $client->increment('reset_attempts');
            $sisa = self::MAX_ATTEMPTS - $client->reset_attempts;

            return back()->withErrors([
                'code' => $client->reset_code_expires_at?->isPast()
                    ? 'Kode sudah kedaluwarsa. Minta kode baru.'
                    : "Kode salah. Sisa percobaan: {$sisa}.",
            ]);
        }

        $client->forceFill(['email_verified_at' => now()])->save();
        $client->clearResetCode();

        $request->session()->forget('verify');

        // Sambutan baru dikirim setelah email terbukti valid — mengirimnya
        // saat pendaftaran hanya akan memantul kalau alamatnya salah.
        try {
            $client->notify(new ClientWelcome());
        } catch (Throwable $e) {
            Log::warning('Email sambutan gagal: ' . $e->getMessage());
        }

        return redirect()->route('client.login')
            ->with('success', 'Email berhasil diverifikasi. Silakan masuk ke akun Anda.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $client = $this->pendingClient($request);

        if (! $client) {
            return redirect()->route('client.login');
        }

        $key = 'verify-resend|' . $client->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['code' => 'Terlalu sering meminta kode. Tunggu ' . RateLimiter::availableIn($key) . ' detik.']);
        }

        RateLimiter::hit($key, 300);

        return $this->sendCode($client)
            ? back()->with('success', 'Kode verifikasi baru sudah dikirim.')
            : back()->withErrors(['code' => 'Kode gagal dikirim. Periksa konfigurasi email server.']);
    }

    private function sendCode(Client $client): bool
    {
        try {
            $client->notify(new VerifyEmailCode($client->generateResetCode()));

            return true;
        } catch (Throwable $e) {
            Log::error('Kode verifikasi email gagal dikirim: ' . $e->getMessage(), ['client_id' => $client->id]);

            return false;
        }
    }

    private function pendingClient(Request $request): ?Client
    {
        $email = $request->session()->get('verify.email');

        return $email ? Client::where('email', $email)->first() : null;
    }
}
