<?php

namespace App\Services\Security;

use App\Models\LoginAttempt;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifikasi "saya bukan robot".
 *
 * Dua mode:
 *  1. reCAPTCHA v2 (kotak centang) — perlu kunci dari Google, paling kuat.
 *  2. Bawaan — pertanyaan penjumlahan sederhana, tidak butuh layanan luar
 *     dan tetap menghentikan bot sederhana. Dipakai kalau kunci Google
 *     belum diisi, supaya form tidak pernah kehilangan proteksi sama sekali.
 *
 * Mode "adaptif" membuat CAPTCHA hanya muncul setelah beberapa kali gagal
 * dari IP yang sama. Ini disengaja: memaksa semua orang mengisi CAPTCHA di
 * setiap login menambah gesekan besar untuk manfaat kecil, sementara bot
 * penebak password justru pasti memicu ambang itu.
 */
class CaptchaService
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /** Berapa kali gagal dari satu IP sebelum CAPTCHA diwajibkan. */
    private const ADAPTIVE_THRESHOLD = 3;

    /**
     * Apakah CAPTCHA perlu ditampilkan pada request ini?
     */
    public function required(Request $request): bool
    {
        $mode = Setting::get('captcha_mode', 'adaptive');

        return match ($mode) {
            'always' => true,
            'off' => false,
            // adaptif: hanya setelah ada percobaan gagal berulang
            default => LoginAttempt::recentFailuresFromIp((string) $request->ip()) >= self::ADAPTIVE_THRESHOLD,
        };
    }

    public function usesRecaptcha(): bool
    {
        return filled(Setting::get('recaptcha_site_key')) && filled(Setting::get('recaptcha_secret_key'));
    }

    public function siteKey(): ?string
    {
        return Setting::get('recaptcha_site_key');
    }

    /**
     * Buat soal untuk CAPTCHA bawaan dan simpan jawabannya di session.
     *
     * @return array{question: string}
     */
    public function makeChallenge(Request $request): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);

        $request->session()->put('captcha_answer', $a + $b);

        return ['question' => "Berapa hasil {$a} + {$b}?"];
    }

    /**
     * Verifikasi jawaban. Mengembalikan null kalau lolos, atau pesan error.
     */
    public function verify(Request $request): ?string
    {
        if (! $this->required($request)) {
            return null;
        }

        return $this->usesRecaptcha()
            ? $this->verifyRecaptcha($request)
            : $this->verifyBuiltin($request);
    }

    private function verifyRecaptcha(Request $request): ?string
    {
        $token = $request->input('g-recaptcha-response');

        if (blank($token)) {
            return 'Centang dulu kotak "Saya bukan robot".';
        }

        try {
            $response = Http::timeout(10)->asForm()->post(self::VERIFY_URL, [
                'secret'   => Setting::get('recaptcha_secret_key'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            if (($response->json('success') ?? false) === true) {
                return null;
            }

            return 'Verifikasi robot gagal. Silakan coba lagi.';
        } catch (Throwable $e) {
            Log::warning('reCAPTCHA tidak bisa dihubungi: ' . $e->getMessage());

            // Google tidak bisa dihubungi. Menolak semua orang di sini akan
            // mengunci pemilik dari panelnya sendiri hanya karena jaringan
            // bermasalah — jadi dibiarkan lolos, tapi tercatat di log.
            return null;
        }
    }

    private function verifyBuiltin(Request $request): ?string
    {
        $expected = $request->session()->pull('captcha_answer');
        $given = $request->input('captcha_answer');

        if ($expected === null) {
            return 'Sesi verifikasi berakhir. Silakan coba lagi.';
        }

        if (! is_numeric($given) || (int) $given !== (int) $expected) {
            return 'Jawaban verifikasi salah.';
        }

        return null;
    }
}
