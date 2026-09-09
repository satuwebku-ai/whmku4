<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Catatan setiap percobaan login, berhasil maupun gagal.
 *
 * Gunanya bukan sekadar arsip: pola percobaan gagal beruntun dari satu IP
 * adalah tanda paling awal seseorang sedang menebak-nebak password. Tanpa
 * catatan ini, serangan seperti itu berlangsung tanpa terlihat sama sekali.
 */
class LoginAttempt extends Model
{
    protected $fillable = [
        'guard', 'identifier', 'successful', 'reason', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['successful' => 'boolean'];
    }

    /**
     * Catat percobaan. Tidak pernah melempar exception — kegagalan mencatat
     * tidak boleh menghalangi orang yang sah untuk masuk.
     */
    public static function record(
        string $guard,
        string $identifier,
        bool $successful,
        ?string $reason = null,
        ?Request $request = null,
    ): void {
        try {
            $request ??= request();

            static::create([
                'guard' => $guard,
                'identifier' => mb_substr($identifier, 0, 190),
                'successful' => $successful,
                'reason' => $reason,
                'ip_address' => $request?->ip(),
                'user_agent' => mb_substr((string) $request?->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mencatat percobaan login: ' . $e->getMessage());
        }
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('successful', false);
    }

    /**
     * Berapa kali gagal dari satu IP dalam rentang waktu tertentu.
     * Dipakai untuk memutuskan kapan CAPTCHA mulai diwajibkan.
     */
    public static function recentFailuresFromIp(string $ip, int $minutes = 15): int
    {
        return static::failed()
            ->where('ip_address', $ip)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'wrong_password'  => 'Password salah',
            'not_found'       => 'Akun tidak ditemukan',
            'inactive'        => 'Akun dinonaktifkan',
            'otp_failed'      => 'Kode OTP salah',
            'captcha_failed'  => 'Verifikasi robot gagal',
            'throttled'       => 'Diblokir sementara',
            'unverified'      => 'Email belum diverifikasi',
            'impersonated'    => 'Diakses admin (bukan login klien)',
            default           => $this->successful ? 'Berhasil' : ($this->reason ?: '—'),
        };
    }
}
