<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Setting;
use App\Notifications\ClientEmailChanged;
use App\Notifications\ClientSecurityAlert;
use App\Notifications\VerifyEmailCode;
use App\Services\ClientSecurityOtp;
use App\Support\Countries;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $client = Auth::guard('client')->user();
        $otp = new ClientSecurityOtp($request);

        return view('client.profile.edit', [
            'client' => $client,
            'countries' => Countries::all(),
            'selectedCountry' => Countries::codeFor($client->country),
            'otpChannels' => $otp->channels($client),
            'otpPending' => [
                'password_change' => $otp->pending('password_change'),
                'two_factor_disable' => $otp->pending('two_factor_disable'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        // Email disimpan huruf kecil & tanpa spasi supaya tidak ada dua akun
        // "A@x.com" dan "a@x.com".
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => [
                'required', 'email', 'max:255', 'unique:clients,email,' . $client->id,
                function (string $attribute, mixed $value, \Closure $fail) use ($client) {
                    if (Client::where('pending_email', $value)->where('id', '!=', $client->id)->exists()) {
                        $fail('Email ini sudah dipakai.');
                    }
                },
            ],
            'current_password' => ['nullable', 'string'],
            'phone'   => ['required', 'string', 'max:30', 'regex:' . PhoneNumber::INPUT_REGEX],
            'company' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city'    => ['nullable', 'string', 'max:120'],
            'state'   => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', Rule::in(array_keys(Countries::all()))],

            'whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:' . PhoneNumber::INPUT_REGEX],
            'notify_promo'    => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
            'notify_sms'      => ['nullable', 'boolean'],
        ], [
            'phone.regex' => 'Format nomor telepon tidak valid. Gunakan angka, boleh diawali +, spasi, atau tanda hubung.',
            'whatsapp_number.regex' => 'Format nomor WhatsApp tidak valid. Gunakan angka, boleh diawali +, spasi, atau tanda hubung.',
            'country.in' => 'Pilih negara dari daftar.',
        ]);

        if (PhoneNumber::normalize($data['phone']) === null) {
            return back()->withInput()->withErrors(['phone' => 'Nomor telepon harus 8–15 digit.']);
        }

        // WhatsApp disimpan dalam format internasional (+62…) supaya nomor
        // luar negeri tidak salah diberi awalan 62 saat pengiriman.
        if (filled($data['whatsapp_number'] ?? null)) {
            $wa = PhoneNumber::normalize($data['whatsapp_number']);

            if ($wa === null) {
                return back()->withInput()->withErrors(['whatsapp_number' => 'Nomor WhatsApp harus 8–15 digit.']);
            }

            $data['whatsapp_number'] = $wa;
        } else {
            $data['whatsapp_number'] = null;
        }

        // Checkbox tidak terkirim saat tidak dicentang, jadi diisi eksplisit.
        $data['notify_promo'] = $request->boolean('notify_promo');

        // WhatsApp hanya bisa diaktifkan kalau nomornya diisi — mengaktifkan
        // tanpa nomor akan membuat notifikasi diam-diam tidak terkirim.
        $data['notify_whatsapp'] = $request->boolean('notify_whatsapp')
            && filled($data['whatsapp_number'] ?? null);

        $data['notify_sms'] = $request->boolean('notify_sms');

        // ── Ganti email ── Email dipakai untuk reset password dan kode login,
        // jadi sesi yang dibajak tidak boleh bisa mengubahnya diam-diam, dan
        // alamat baru harus dibuktikan SEBELUM menggantikan yang lama.
        $newEmail = $data['email'];
        unset($data['email'], $data['current_password']);

        $emailChanged = $newEmail !== mb_strtolower($client->email);

        if ($emailChanged && $client->google_id) {
            // Akun Google: email adalah identitas Google-nya, jadi dikunci.
            $emailChanged = false;
        }

        if ($emailChanged) {
            $throttleKey = 'client-email-change|' . $client->id;

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                return back()->withInput()->withErrors([
                    'current_password' => 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil(RateLimiter::availableIn($throttleKey) / 60) . ' menit.',
                ]);
            }

            $password = (string) $request->input('current_password');

            if ($password === '' || ! Hash::check($password, (string) $client->password)) {
                RateLimiter::hit($throttleKey, 900);

                return back()->withInput()->withErrors([
                    'current_password' => 'Untuk mengganti email, masukkan password Anda saat ini dengan benar.',
                ]);
            }

            RateLimiter::clear($throttleKey);
        }

        $client->update($data);

        if (! $emailChanged) {
            return back()->with('success', 'Data profil berhasil diperbarui.');
        }

        try {
            $this->sendEmailChangeCode($client, $newEmail);
        } catch (Throwable $e) {
            Log::error('Gagal mengirim kode verifikasi email baru: ' . $e->getMessage(), ['client_id' => $client->id]);
            $client->clearPendingEmail();

            return back()->withInput()->withErrors([
                'email' => 'Kode verifikasi gagal dikirim ke email baru. Periksa alamatnya lalu coba lagi.',
            ]);
        }

        $this->alert($client, 'permintaan ganti email ke ' . $this->maskEmail($newEmail), $request);
        $this->record($client, 'Klien meminta ganti email');

        return back()->with('success', 'Data profil diperbarui. Kami mengirim kode ke ' . $newEmail . ' — email baru baru aktif setelah kode dimasukkan.');
    }

    public function verifyEmail(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        $data = $request->validate(['code' => ['required', 'digits:6']]);

        if (! $client->pending_email) {
            return back()->with('error', 'Tidak ada penggantian email yang sedang menunggu.');
        }

        $throttleKey = 'client-email-verify|' . $client->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            return back()->withErrors(['code' => 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil(RateLimiter::availableIn($throttleKey) / 60) . ' menit.']);
        }

        if (! $client->pendingEmailCodeIsValid($data['code'])) {
            RateLimiter::hit($throttleKey, 900);
            $client->forceFill(['pending_email_attempts' => $client->pending_email_attempts + 1])->save();

            if ($client->pending_email_attempts >= 5) {
                $client->clearPendingEmail();

                return back()->with('error', 'Terlalu banyak kode salah. Penggantian email dibatalkan — ulangi dari awal.');
            }

            return back()->withErrors(['code' => 'Kode salah atau sudah kedaluwarsa.']);
        }

        RateLimiter::clear($throttleKey);

        $newEmail = $client->pending_email;

        // Alamat bisa saja terpakai akun lain selama menunggu kode.
        if (Client::where('email', $newEmail)->where('id', '!=', $client->id)->exists()) {
            $client->clearPendingEmail();

            return back()->with('error', 'Email tersebut sudah dipakai akun lain. Penggantian dibatalkan.');
        }

        $oldEmail = $client->email;

        $client->forceFill(['email' => $newEmail, 'email_verified_at' => now()])->save();
        $client->clearPendingEmail();

        $this->notifyOldEmail($oldEmail, $newEmail, $request->ip());
        $this->record($client, 'Klien mengganti email (terverifikasi)');

        return back()->with('success', 'Email berhasil diganti menjadi ' . $newEmail . '.');
    }

    public function resendEmailCode(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        if (! $client->pending_email) {
            return back()->with('error', 'Tidak ada penggantian email yang sedang menunggu.');
        }

        $throttleKey = 'client-email-resend|' . $client->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return back()->with('error', 'Terlalu banyak permintaan kode. Coba lagi dalam ' . ceil(RateLimiter::availableIn($throttleKey) / 60) . ' menit.');
        }

        RateLimiter::hit($throttleKey, 600);

        try {
            $this->sendEmailChangeCode($client, $client->pending_email);
        } catch (Throwable $e) {
            Log::error('Gagal mengirim ulang kode verifikasi email: ' . $e->getMessage(), ['client_id' => $client->id]);

            return back()->with('error', 'Kode gagal dikirim. Coba lagi sebentar lagi.');
        }

        return back()->with('success', 'Kode baru dikirim ke ' . $client->pending_email . '.');
    }

    public function cancelEmailChange(): RedirectResponse
    {
        Auth::guard('client')->user()->clearPendingEmail();

        return back()->with('success', 'Penggantian email dibatalkan.');
    }

    /**
     * Minta kode OTP (email atau WhatsApp) untuk tindakan sensitif.
     */
    public function sendOtp(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        $data = $request->validate([
            'purpose' => ['required', Rule::in(array_keys(ClientSecurityOtp::ACTIONS))],
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
        ]);

        try {
            (new ClientSecurityOtp($request))->send($client, $data['purpose'], $data['channel']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Gagal mengirim OTP keamanan: ' . $e->getMessage(), ['client_id' => $client->id, 'channel' => $data['channel']]);

            return back()->with('error', 'Kode gagal dikirim. Coba kanal lain atau ulangi sebentar lagi.');
        }

        return back()->with('success', 'Kode verifikasi dikirim lewat ' . ($data['channel'] === 'whatsapp' ? 'WhatsApp' : 'email') . '. Berlaku 10 menit.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();
        $viaOtp = $client->requiresOtpForSensitive();

        $data = $request->validate([
            'current_password' => [$viaOtp ? 'nullable' : 'required', 'string'],
            'otp'              => [$viaOtp ? 'required' : 'nullable', 'digits:6'],
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'otp.required' => 'Masukkan kode verifikasi yang dikirim ke email/WhatsApp Anda.',
            'otp.digits' => 'Kode verifikasi terdiri dari 6 angka.',
        ]);

        $throttleKey = 'client-password-change|' . $client->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()->withErrors([
                $viaOtp ? 'otp' : 'current_password' => 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil(RateLimiter::availableIn($throttleKey) / 60) . ' menit.',
            ]);
        }

        if ($viaOtp) {
            if (! (new ClientSecurityOtp($request))->verify('password_change', $data['otp'])) {
                RateLimiter::hit($throttleKey, 900);

                return back()->withErrors(['otp' => 'Kode salah atau sudah kedaluwarsa. Minta kode baru bila perlu.']);
            }
        } elseif (! Hash::check($data['current_password'], $client->password)) {
            RateLimiter::hit($throttleKey, 900);

            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        if (Hash::check($data['password'], (string) $client->password)) {
            return back()->withErrors(['password' => 'Password baru harus berbeda dari password saat ini.']);
        }

        RateLimiter::clear($throttleKey);

        $client->forceFill([
            'password' => $data['password'],
            'password_set_by_user' => true,
            'remember_token' => Str::random(60),
        ])->save();

        // Sesi ini tetap masuk; sesi di perangkat lain gugur lewat ClientMiddleware.
        $request->session()->put('client_password_stamp', ['id' => $client->getAuthIdentifier(), 'hash' => (string) $client->getAuthPassword()]);
        $request->session()->regenerate();

        $this->alert($client, 'password diganti', $request);
        $this->record($client, 'Klien mengganti password');

        return back()->with('success', 'Password berhasil diganti. Perangkat lain otomatis dikeluarkan.');
    }

    /**
     * Klien memilih apakah ganti password harus lewat kode OTP.
     * Mengaktifkan bebas; mematikan butuh password (kalau tidak, sesi yang
     * dibajak bisa melemahkan proteksi). Akun Google tanpa password sendiri
     * selalu memakai OTP, jadi pilihannya tidak berlaku.
     */
    public function togglePasswordOtp(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        if (! $client->passwordKnownToUser()) {
            return back()->with('error', 'Akun Anda memang selalu memakai kode OTP untuk ganti password.');
        }

        if (! $client->password_otp_enabled) {
            $client->update(['password_otp_enabled' => true]);
            $this->alert($client, 'verifikasi OTP untuk ganti password diaktifkan', $request);
            $this->record($client, 'Klien mengaktifkan OTP ganti password');

            return back()->with('success', 'Ganti password sekarang memakai kode OTP (email/WhatsApp).');
        }

        $request->validate(['current_password' => ['required', 'string']]);

        $throttleKey = 'client-password-otp-toggle|' . $client->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()->withErrors(['current_password' => 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil(RateLimiter::availableIn($throttleKey) / 60) . ' menit.']);
        }

        if (! Hash::check((string) $request->input('current_password'), (string) $client->password)) {
            RateLimiter::hit($throttleKey, 900);

            return back()->withErrors(['current_password' => 'Password salah. Pengaturan tidak diubah.']);
        }

        RateLimiter::clear($throttleKey);

        $client->update(['password_otp_enabled' => false]);
        $this->alert($client, 'verifikasi OTP untuk ganti password dinonaktifkan', $request);
        $this->record($client, 'Klien menonaktifkan OTP ganti password');

        return back()->with('success', 'Ganti password kembali memakai password saat ini.');
    }

    public function toggleTwoFactor(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        // Menonaktifkan 2FA butuh konfirmasi — kalau tidak, sesi yang
        // dibajak bisa mematikan proteksi tanpa hambatan.
        if ($client->two_factor_enabled) {
            $throttleKey = 'client-2fa-toggle|' . $client->id;

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                return back()->withErrors([
                    'current_password' => 'Terlalu banyak percobaan. Coba lagi dalam ' . ceil(RateLimiter::availableIn($throttleKey) / 60) . ' menit.',
                ]);
            }

            if ($client->requiresOtpForSensitive()) {
                $request->validate(['otp' => ['required', 'digits:6']], ['otp.required' => 'Masukkan kode verifikasi.', 'otp.digits' => 'Kode verifikasi terdiri dari 6 angka.']);

                if (! (new ClientSecurityOtp($request))->verify('two_factor_disable', (string) $request->input('otp'))) {
                    RateLimiter::hit($throttleKey, 900);

                    return back()->withErrors(['otp' => 'Kode salah atau sudah kedaluwarsa. 2FA tidak dinonaktifkan.']);
                }
            } else {
                $request->validate(['current_password' => ['required', 'string']]);

                if (! Hash::check($request->input('current_password'), $client->password)) {
                    RateLimiter::hit($throttleKey, 900);

                    return back()->withErrors(['current_password' => 'Password salah. 2FA tidak dinonaktifkan.']);
                }
            }

            RateLimiter::clear($throttleKey);
        }

        $client->update(['two_factor_enabled' => ! $client->two_factor_enabled]);
        $client->clearOtp();

        $this->alert($client, $client->two_factor_enabled ? '2FA diaktifkan' : '2FA dinonaktifkan', $request);
        $this->record($client, $client->two_factor_enabled ? 'Klien mengaktifkan 2FA' : 'Klien menonaktifkan 2FA');

        return back()->with('success', $client->two_factor_enabled
            ? 'Verifikasi dua langkah AKTIF. Login berikutnya akan meminta kode dari email Anda.'
            : 'Verifikasi dua langkah dinonaktifkan.');
    }

    /**
     * Kirim kode ke alamat BARU. Memakai model sementara (tidak disimpan)
     * supaya notifikasi memakai nama klien tapi dialamatkan ke email baru.
     */
    private function sendEmailChangeCode(Client $client, string $newEmail): void
    {
        $code = $client->startEmailChange($newEmail);

        $target = new Client(['name' => $client->name]);
        $target->email = $newEmail;
        $target->notify(new VerifyEmailCode($code));
    }

    /**
     * Beri tahu alamat lama. Gagal kirim tidak boleh membatalkan perubahan
     * (SMTP bisa sedang bermasalah) -- cukup dicatat.
     */
    private function notifyOldEmail(string $oldEmail, string $newEmail, ?string $ip): void
    {
        try {
            Notification::route('mail', $oldEmail)->notify(new ClientEmailChanged(
                $this->maskEmail($newEmail),
                (string) (Setting::get('site_name') ?: config('app.name')),
                $ip,
            ));
        } catch (Throwable $e) {
            Log::warning('Peringatan ganti email ke alamat lama gagal terkirim: ' . $e->getMessage());
        }
    }

    /**
     * Pemberitahuan keamanan ke email aktif klien. Tidak pernah membatalkan
     * tindakan kalau gagal terkirim.
     */
    private function alert(Client $client, string $event, Request $request): void
    {
        try {
            $client->notify(new ClientSecurityAlert(
                $event,
                (string) (Setting::get('site_name') ?: config('app.name')),
                $request->ip(),
            ));
        } catch (Throwable $e) {
            Log::warning('Pemberitahuan keamanan akun gagal terkirim: ' . $e->getMessage(), ['client_id' => $client->id]);
        }
    }

    private function record(Client $client, string $title): void
    {
        ActivityLog::record('client', $title, $client->name . ' (' . $client->email . ')', null, 'info', $client->id);
    }

    private function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($user, 0, 1) . str_repeat('*', max(2, mb_strlen($user) - 1)) . '@' . $domain;
    }
}
