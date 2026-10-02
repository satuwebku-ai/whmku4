<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('client.profile.edit', ['client' => Auth::guard('client')->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255', 'unique:clients,email,' . $client->id],
            'current_password' => ['nullable', 'string'],
            'phone'   => ['required', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city'    => ['nullable', 'string', 'max:120'],
            'state'   => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:120'],

            'whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]*$/'],
            'notify_promo'    => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
            'notify_sms'      => ['nullable', 'boolean'],
        ], [
            'whatsapp_number.regex' => 'Nomor WhatsApp hanya boleh berisi angka.',
        ]);

        // Checkbox tidak terkirim saat tidak dicentang, jadi diisi eksplisit.
        $data['notify_promo'] = $request->boolean('notify_promo');

        // WhatsApp hanya bisa diaktifkan kalau nomornya diisi — mengaktifkan
        // tanpa nomor akan membuat notifikasi diam-diam tidak terkirim.
        $data['notify_whatsapp'] = $request->boolean('notify_whatsapp')
            && filled($data['whatsapp_number'] ?? null);

        $data['notify_sms'] = $request->boolean('notify_sms');

        // ── Ganti email ── Email dipakai untuk reset password dan kode login,
        // jadi sesi yang dibajak tidak boleh bisa mengubahnya diam-diam.
        $oldEmail = $client->email;
        $emailChanged = mb_strtolower(trim($data['email'])) !== mb_strtolower($oldEmail);

        if ($emailChanged && $client->google_id) {
            // Akun Google: email adalah identitas Google-nya dan password
            // acaknya tidak diketahui klien, jadi tidak ada cara aman
            // memverifikasi pemiliknya di sini. Email dikunci.
            $data['email'] = $oldEmail;
            $emailChanged = false;
        }

        if ($emailChanged) {
            $throttleKey = 'client-email-change|' . $client->id;

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                return back()->withInput()->withErrors([
                    'current_password' => 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil(RateLimiter::availableIn($throttleKey) / 60) . ' menit.',
                ]);
            }

            if (blank($data['current_password'] ?? null) || ! Hash::check($data['current_password'], (string) $client->password)) {
                RateLimiter::hit($throttleKey, 900);

                return back()->withInput()->withErrors([
                    'current_password' => 'Untuk mengganti email, masukkan password Anda saat ini dengan benar.',
                ]);
            }

            RateLimiter::clear($throttleKey);

            // Alamat baru harus dibuktikan dulu: salah ketik atau alamat
            // orang lain tidak boleh langsung jadi penerima kode reset.
            // Login berikutnya akan meminta kode verifikasi ke alamat baru.
            $data['email_verified_at'] = null;
        }

        unset($data['current_password']);

        $client->update($data);

        if ($emailChanged) {
            $this->notifyOldEmail($oldEmail, $data['email'], $request->ip());
        }

        return back()->with('success', $emailChanged
            ? 'Data profil diperbarui. Email baru perlu diverifikasi saat Anda masuk berikutnya.'
            : 'Data profil berhasil diperbarui.');
    }

    /**
     * Beri tahu alamat lama. Gagal kirim tidak boleh membatalkan perubahan
     * (SMTP bisa sedang bermasalah) -- cukup dicatat.
     */
    private function notifyOldEmail(string $oldEmail, string $newEmail, ?string $ip): void
    {
        try {
            Notification::route('mail', $oldEmail)->notify(new \App\Notifications\ClientEmailChanged(
                $this->maskEmail($newEmail),
                (string) (\App\Models\Setting::get('site_name') ?: config('app.name')),
                $ip,
            ));
        } catch (Throwable $e) {
            Log::warning('Peringatan ganti email ke alamat lama gagal terkirim: ' . $e->getMessage());
        }
    }

    private function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($user, 0, 1) . str_repeat('*', max(2, mb_strlen($user) - 1)) . '@' . $domain;
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $throttleKey = 'client-password-change|' . $client->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()->withErrors([
                'current_password' => 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil(RateLimiter::availableIn($throttleKey) / 60) . ' menit.',
            ]);
        }

        if (! Hash::check($data['current_password'], $client->password)) {
            RateLimiter::hit($throttleKey, 900);

            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        RateLimiter::clear($throttleKey);

        $client->update(['password' => $data['password']]);

        return back()->with('success', 'Password berhasil diganti.');
    }

    public function toggleTwoFactor(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        // Menonaktifkan 2FA butuh konfirmasi password — kalau tidak,
        // sesi yang dibajak bisa mematikan proteksi tanpa hambatan.
        if ($client->two_factor_enabled) {
            $request->validate(['current_password' => ['required', 'string']]);

            if (! Hash::check($request->input('current_password'), $client->password)) {
                return back()->withErrors(['current_password' => 'Password salah. 2FA tidak dinonaktifkan.']);
            }
        }

        $client->update(['two_factor_enabled' => ! $client->two_factor_enabled]);
        $client->clearOtp();

        return back()->with('success', $client->two_factor_enabled
            ? 'Verifikasi dua langkah AKTIF. Login berikutnya akan meminta kode dari email Anda.'
            : 'Verifikasi dua langkah dinonaktifkan.');
    }
}
