<?php

namespace App\Http\Controllers\Auth\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Notifications\SendPasswordResetCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

/**
 * Alur lupa password untuk admin. Sebelumnya HANYA Client yang punya
 * alur ini -- admin yang lupa password harus minta superadmin mengubahkan
 * manual lewat panel Admin & Akses. Struktur & pola persis meniru
 * Auth\Client\ForgotPasswordController (email → kode → password baru),
 * memakai kolom reset_code_hash/reset_code_expires_at/reset_attempts
 * yang baru ditambahkan ke tabel admins.
 */
class ForgotPasswordController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function request(): View
    {
        return view('admin.auth.forgot');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $key = 'admin-reset-code|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors([
                'email' => 'Terlalu banyak permintaan. Coba lagi dalam ' . RateLimiter::availableIn($key) . ' detik.',
            ]);
        }

        RateLimiter::hit($key, 600);

        $admin = Admin::where('email', $data['email'])->first();

        // Pesan sengaja dibuat sama baik email terdaftar maupun tidak,
        // supaya halaman ini tidak bisa dipakai menebak email admin.
        $genericMessage = 'Kalau email tersebut terdaftar sebagai admin, kami sudah mengirimkan kode reset ke sana.';

        if ($admin) {
            if (! $admin->is_active) {
                // Tidak diberi tahu ke pemohon (tetap pesan generik di
                // atas) -- tapi kode TIDAK dikirim untuk akun nonaktif.
                Log::info('Percobaan reset password untuk admin nonaktif.', ['admin_id' => $admin->id]);
            } else {
                try {
                    $admin->notify(new SendPasswordResetCode($admin->generateResetCode()));
                } catch (Throwable $e) {
                    Log::error('Gagal mengirim kode reset admin: ' . $e->getMessage(), ['admin_id' => $admin->id]);

                    return back()->withErrors([
                        'email' => 'Kode gagal dikirim karena masalah pada server email. Silakan hubungi superadmin.',
                    ]);
                }
            }
        }

        $request->session()->put('admin_reset.email', $data['email']);

        return redirect()->route('admin.password.verify')->with('success', $genericMessage);
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('admin_reset.email')) {
            return redirect()->route('admin.password.request');
        }

        return view('admin.auth.verify-code', [
            'email' => $request->session()->get('admin_reset.email'),
        ]);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $admin = $this->pendingAdmin($request);

        if (! $admin) {
            return redirect()->route('admin.password.request')
                ->withErrors(['email' => 'Sesi berakhir. Silakan minta kode baru.']);
        }

        if ($admin->reset_attempts >= self::MAX_ATTEMPTS) {
            $admin->clearResetCode();
            $request->session()->forget('admin_reset');

            return redirect()->route('admin.password.request')
                ->withErrors(['email' => 'Terlalu banyak percobaan kode. Silakan minta kode baru.']);
        }

        if (! $admin->resetCodeIsValid($data['code'])) {
            $admin->increment('reset_attempts');
            $sisa = self::MAX_ATTEMPTS - $admin->reset_attempts;

            return back()->withErrors([
                'code' => $admin->reset_code_expires_at?->isPast()
                    ? 'Kode sudah kedaluwarsa. Minta kode baru.'
                    : "Kode salah. Sisa percobaan: {$sisa}.",
            ]);
        }

        $request->session()->put('admin_reset.verified', true);

        return redirect()->route('admin.password.reset');
    }

    public function resetForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('admin_reset.verified')) {
            return redirect()->route('admin.password.request');
        }

        return view('admin.auth.reset');
    }

    public function reset(Request $request): RedirectResponse
    {
        if (! $request->session()->get('admin_reset.verified')) {
            return redirect()->route('admin.password.request');
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $admin = $this->pendingAdmin($request);

        if (! $admin) {
            return redirect()->route('admin.password.request');
        }

        $admin->update(['password' => $data['password']]);
        $admin->clearResetCode();

        $request->session()->forget('admin_reset');

        return redirect()->route('admin.login')
            ->with('success', 'Password berhasil diubah. Silakan masuk dengan password baru Anda.');
    }

    private function pendingAdmin(Request $request): ?Admin
    {
        $email = $request->session()->get('admin_reset.email');

        return $email ? Admin::where('email', $email)->first() : null;
    }
}
