<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Setting;
use App\Notifications\SecurityOtpCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * OTP untuk tindakan sensitif di profil klien. Kode disimpan (hash) di
 * SESI yang sedang login, jadi hanya berlaku di browser yang memintanya.
 */
class ClientSecurityOtp
{
    public const ACTIONS = [
        'password_change' => 'ganti password',
        'two_factor_disable' => 'menonaktifkan 2FA',
    ];

    private const TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private Request $request) {}

    public function whatsappAvailable(Client $client): bool
    {
        return Setting::get('wa_provider', 'none') !== 'none'
            && filled(Setting::get('wa_token'))
            && filled($client->whatsapp_number ?: $client->phone);
    }

    /**
     * @return array<string, string> kanal => label
     */
    public function channels(Client $client): array
    {
        $channels = ['email' => 'Email (' . $this->maskEmail($client->email) . ')'];

        if ($this->whatsappAvailable($client)) {
            $channels['whatsapp'] = 'WhatsApp (' . $this->maskNumber((string) ($client->whatsapp_number ?: $client->phone)) . ')';
        }

        return $channels;
    }

    /**
     * @throws RuntimeException pesan yang aman ditampilkan ke klien
     */
    public function send(Client $client, string $purpose, string $channel): void
    {
        if (! isset(self::ACTIONS[$purpose])) {
            throw new RuntimeException('Jenis verifikasi tidak dikenal.');
        }

        if (! array_key_exists($channel, $this->channels($client))) {
            throw new RuntimeException('Kanal pengiriman tidak tersedia untuk akun Anda.');
        }

        $key = 'client-sec-otp|' . $client->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new RuntimeException('Terlalu banyak permintaan kode. Coba lagi dalam ' . ceil(RateLimiter::availableIn($key) / 60) . ' menit.');
        }

        RateLimiter::hit($key, 600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Kirim dulu; kalau gagal, kode lama di sesi tidak ditimpa.
        $client->notify(new SecurityOtpCode($code, self::ACTIONS[$purpose], $channel));

        $this->request->session()->put($this->sessionKey($purpose), [
            'hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->timestamp,
            'channel' => $channel,
            'attempts' => 0,
        ]);
    }

    /**
     * Info kode yang sedang menunggu (untuk ditampilkan di form), atau null.
     *
     * @return array{channel: string, expires_at: int}|null
     */
    public function pending(string $purpose): ?array
    {
        $state = $this->request->session()->get($this->sessionKey($purpose));

        if (! is_array($state) || ($state['expires_at'] ?? 0) < now()->timestamp) {
            return null;
        }

        return ['channel' => $state['channel'], 'expires_at' => $state['expires_at']];
    }

    public function verify(string $purpose, string $code): bool
    {
        $session = $this->request->session();
        $key = $this->sessionKey($purpose);
        $state = $session->get($key);

        if (! is_array($state) || ($state['expires_at'] ?? 0) < now()->timestamp) {
            $session->forget($key);

            return false;
        }

        if (($state['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            $session->forget($key);

            return false;
        }

        if (! Hash::check($code, $state['hash'])) {
            $state['attempts'] = ($state['attempts'] ?? 0) + 1;
            $session->put($key, $state);

            return false;
        }

        $session->forget($key);

        return true;
    }

    private function sessionKey(string $purpose): string
    {
        return 'client_security_otp.' . $purpose;
    }

    private function maskEmail(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($user, 0, 1) . str_repeat('*', max(2, mb_strlen($user) - 1)) . '@' . $domain;
    }

    private function maskNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        return strlen($digits) > 5 ? substr($digits, 0, 3) . str_repeat('*', strlen($digits) - 5) . substr($digits, -2) : '***';
    }
}
