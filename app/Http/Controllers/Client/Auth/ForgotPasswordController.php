<?php

namespace App\Http\Controllers\Client\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Notifications\SendPasswordResetCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class ForgotPasswordController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function request(): View
    {
        return view('client.auth.forgot');
    }

    /**
     * Kirim kode reset ke email.
     */
    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Dibatasi agar tidak bisa dipakai membanjiri email orang lain.
        $key = 'reset-code|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'email' => 'Terlalu banyak permintaan. Coba lagi dalam ' . RateLimiter::availableIn($key) . ' detik.',
            ]);
        }

        RateLimiter::hit($key, 600);

        $client = Client::where('email', $data['email'])->first();

        // Pesan sengaja dibuat sama baik email terdaftar maupun tidak,
        // supaya halaman ini tidak bisa dipakai menebak email pelanggan.
        $genericMessage = 'Kalau email tersebut terdaftar, kami sudah mengirimkan kode reset ke sana.';

        if ($client) {
            try {
                $client->notify(new SendPasswordResetCode($client->generateResetCode()));
            } catch (Throwable $e) {
                Log::error('Gagal mengirim kode reset: ' . $e->getMessage(), ['client_id' => $client->id]);

                return back()->withErrors([
                    'email' => 'Kode gagal dikirim karena masalah pada server email. Silakan hubungi kami lewat kontak support.',
                ]);
            }
        }

        $request->session()->put('reset.email', $data['email']);

        return redirect()->route('client.password.verify')->with('success', $genericMessage);
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('reset.email')) {
            return redirect()->route('client.password.request');
        }

        return view('client.auth.verify-code', [
            'email' => $request->session()->get('reset.email'),
        ]);
    }

    /**
     * Cek kode, lalu izinkan menetapkan password baru.
     */
    public function verifyCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $client = $this->pendingClient($request);

        if (! $client) {
            return redirect()->route('client.password.request')
                ->withErrors(['email' => 'Sesi berakhir. Silakan minta kode baru.']);
        }

        if ($client->reset_attempts >= self::MAX_ATTEMPTS) {
            $client->clearResetCode();
            $request->session()->forget('reset');

            return redirect()->route('client.password.request')
                ->withErrors(['email' => 'Terlalu banyak percobaan kode. Silakan minta kode baru.']);
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

        // Kode benar — tandai sesi boleh menetapkan password baru.
        $request->session()->put('reset.verified', true);

        return redirect()->route('client.password.reset');
    }

    public function resetForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('reset.verified')) {
            return redirect()->route('client.password.request');
        }

        return view('client.auth.reset');
    }

    public function reset(Request $request): RedirectResponse
    {
        if (! $request->session()->get('reset.verified')) {
            return redirect()->route('client.password.request');
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $client = $this->pendingClient($request);

        if (! $client) {
            return redirect()->route('client.password.request');
        }

        $client->update(['password' => $data['password']]);
        $client->clearResetCode();

        $request->session()->forget('reset');

        return redirect()->route('client.login')
            ->with('success', 'Password berhasil diubah. Silakan masuk dengan password baru Anda.');
    }

    private function pendingClient(Request $request): ?Client
    {
        $email = $request->session()->get('reset.email');

        return $email ? Client::where('email', $email)->first() : null;
    }
}
