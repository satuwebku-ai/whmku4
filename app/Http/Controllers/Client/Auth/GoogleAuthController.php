<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LoginAttempt;
use App\Services\Notification\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Login/daftar klien lewat akun Google.
 *
 * Kenapa email TIDAK perlu verifikasi ulang lewat kode 6-digit kami:
 * Google sudah memverifikasi kepemilikan email itu sebelum mengizinkan
 * orang login dengan akunnya. Meminta verifikasi kedua hanya mengulang
 * pekerjaan yang sudah dilakukan Google — email_verified_at langsung
 * diisi begitu login Google berhasil, termasuk untuk akun lama yang
 * sebelumnya daftar manual tapi belum sempat verifikasi.
 *
 * Password diisi acak (bukan dikosongkan) supaya kolom tetap konsisten
 * dengan constraint database dan tidak ada celah "password kosong" kalau
 * suatu saat validasi login berubah.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('client.login')
                ->with('error', 'Login dengan Google belum diaktifkan di situs ini.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('client.login');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Login Google gagal: ' . $e->getMessage());

            return redirect()->route('client.login')
                ->with('error', 'Login dengan Google dibatalkan atau gagal. Silakan coba lagi.');
        }

        $email = $googleUser->getEmail();

        if (blank($email)) {
            return redirect()->route('client.login')
                ->with('error', 'Akun Google Anda tidak memiliki email yang bisa dipakai.');
        }

        $client = Client::where('google_id', $googleUser->getId())
            ->orWhere('email', $email)
            ->first();

        if ($client) {
            // Akun lama (daftar manual) login Google pertama kali dengan
            // email yang sama — ditautkan, bukan dibuat duplikat.
            $client->forceFill([
                'google_id' => $client->google_id ?: $googleUser->getId(),
                'avatar' => $googleUser->getAvatar() ?: $client->avatar,
                'email_verified_at' => $client->email_verified_at ?: now(),
            ])->save();
        } else {
            $client = Client::create([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'password' => Str::password(32),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            try {
                app(NotificationService::class)->clientRegistered($client);
            } catch (Throwable $e) {
                Log::warning('Notifikasi pendaftaran Google gagal: ' . $e->getMessage());
            }
        }

        if (! $client->isActive()) {
            LoginAttempt::record('client', $email, false, 'inactive');

            return redirect()->route('client.login')
                ->with('error', 'Akun Anda sedang tidak aktif. Silakan hubungi tim support kami.');
        }

        Auth::guard('client')->login($client, true);
        request()->session()->regenerate();

        $client->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->save();

        LoginAttempt::record('client', $email, true, null);

        return redirect()->route('client.dashboard')->with('success', 'Berhasil masuk dengan Google.');
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }
}
