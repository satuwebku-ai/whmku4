<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class Client extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'address', 'client_group_id',
        'city', 'state', 'postal_code', 'country', 'password', 'status', 'internal_notes',
        'email_verified_at', 'last_login_at', 'last_login_ip',
        'whatsapp_number', 'notify_promo', 'notify_whatsapp', 'notify_sms',
        'google_id', 'avatar', 'two_factor_enabled',
        'pending_email', 'pending_email_code_hash', 'pending_email_expires_at', 'pending_email_attempts',
        'password_otp_enabled', 'password_set_by_user',
    ];

    protected $hidden = ['password', 'remember_token', 'internal_notes'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'pending_email_expires_at' => 'datetime',
            'password_otp_enabled' => 'boolean',
            'password_set_by_user' => 'boolean',
            'reset_code_expires_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'notify_promo' => 'boolean',
            'notify_whatsapp' => 'boolean',
            'notify_sms' => 'boolean',
            'last_login_at' => 'datetime',
            'balance' => 'decimal:2',
        ];
    }

    /**
     * Satu-satunya jalan resmi untuk mengubah saldo — supaya TIDAK ADA
     * jalan pintas yang mengubah $client->balance langsung tanpa jejak
     * di buku besar (client_balance_logs). $amount boleh negatif (untuk
     * mengurangi), boleh positif (untuk menambah).
     */
    public function adjustBalance(
        float|int|string $amount,
        string $type,
        string $description,
        ?\App\Models\Invoice $invoice = null,
        ?\App\Models\Admin $admin = null,
        ?string $idempotencyKey = null,
        bool $allowNegative = false,
    ): \App\Models\Credit
    {
        if (! array_key_exists($type, \App\Models\Credit::TYPES)) {
            throw new \InvalidArgumentException("Jenis mutasi saldo tidak dikenal: {$type}");
        }

        return DB::transaction(function () use ($amount, $type, $description, $invoice, $admin, $idempotencyKey, $allowNegative) {
            // Kunci klien DULU, baru cek kunci idempotensi: semua mutasi
            // saldo satu klien berjalan bergantian, jadi dua permintaan
            // dengan kunci sama tidak bisa sama-sama lolos pengecekan.
            $client = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($idempotencyKey) {
                $existing = \App\Models\Credit::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $deltaCents = \App\Support\Money::cents($amount);
            $newCents = \App\Support\Money::cents($client->balance) + $deltaCents;

            // Satu-satunya tempat saldo dijaga agar tidak minus; refund /
            // chargeback yang harus menarik dana yang sudah terpakai
            // boleh minus lewat $allowNegative (jadi utang yang terlihat).
            if ($deltaCents < 0 && $newCents < 0 && ! $allowNegative) {
                throw new \App\Exceptions\Billing\BillingException('Saldo tidak cukup untuk transaksi ini.');
            }

            $newBalance = \App\Support\Money::fromCents($newCents);

            // balance sengaja tidak ada di $fillable: hanya method ini yang boleh
            // menulisnya, supaya mass assignment tidak bisa melewati ledger.
            $client->forceFill(['balance' => $newBalance])->save();

            // Samakan instance pemanggil supaya $client->balance langsung benar.
            $this->forceFill(['balance' => $newBalance])->syncOriginalAttribute('balance');

            return \App\Models\Credit::create([
                'client_id' => $client->id,
                'amount' => \App\Support\Money::fromCents($deltaCents),
                'type' => $type,
                'description' => $description,
                'invoice_id' => $invoice?->id,
                'admin_id' => $admin?->id,
                'balance_after' => $newBalance,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    /**
     * Buat kode reset 6 digit, simpan hash-nya, kembalikan kode aslinya
     * untuk dikirim lewat email.
     */
    public function generateResetCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'reset_code_hash' => \Illuminate\Support\Facades\Hash::make($code),
            'reset_code_expires_at' => now()->addMinutes(15),
            'reset_attempts' => 0,
        ])->save();

        return $code;
    }

    public function resetCodeIsValid(string $code): bool
    {
        if (! $this->reset_code_hash || ! $this->reset_code_expires_at) {
            return false;
        }

        if ($this->reset_code_expires_at->isPast()) {
            return false;
        }

        return \Illuminate\Support\Facades\Hash::check($code, $this->reset_code_hash);
    }

    public function clearResetCode(): void
    {
        $this->forceFill([
            'reset_code_hash' => null,
            'reset_code_expires_at' => null,
            'reset_attempts' => 0,
        ])->save();
    }

    /**
     * Nomor tujuan notifikasi WhatsApp. Dipakai otomatis oleh channel
     * WhatsApp — jatuh ke nomor telepon biasa kalau kolom khususnya kosong.
     */
    public function routeNotificationForWhatsApp(): ?string
    {
        return $this->whatsapp_number ?: $this->phone;
    }

    public function hostingAccounts(): HasMany
    {
        return $this->hasMany(HostingAccount::class);
    }

    public function pushSubscriptions(): MorphMany
    {
        return $this->morphMany(PushSubscription::class, 'subscribable');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function balanceLogs(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Profil affiliate MILIK client ini (kalau ia mendaftar jadi
     * affiliate lewat Client\AffiliateController) -- bukan affiliate
     * yang MEREFERENSIKAN client ini, itu affiliateReferral() di bawah.
     */
    public function affiliate(): HasOne
    {
        return $this->hasOne(Affiliate::class);
    }

    /**
     * Atribusi: affiliate mana yang mereferensikan client ini saat
     * mendaftar (kalau ada). Lihat App\Services\Affiliate\AffiliateTrackingService.
     */
    public function affiliateReferral(): HasOne
    {
        return $this->hasOne(AffiliateReferral::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ClientGroup::class, 'client_group_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', trim($this->name));
        $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));

        return $initials ?: 'NA';
    }

    public function getAvatarUrlAttribute(): string
    {
        return \App\Support\InitialsAvatar::dataUri((string) $this->name);
    }

    /**
     * Klien nonaktif tidak boleh login ke client area.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Buat OTP 6 digit, simpan hash-nya, kembalikan kode aslinya untuk
     * dikirim lewat email. Kode mentah tidak pernah disimpan -- sama
     * persis pola yang sudah terbukti di Admin::generateOtp().
     */
    public function generateOtp(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'otp_code_hash'  => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts'   => 0,
        ])->save();

        return $code;
    }

    public function otpIsValid(string $code): bool
    {
        if (! $this->otp_code_hash || ! $this->otp_expires_at) {
            return false;
        }

        if ($this->otp_expires_at->isPast()) {
            return false;
        }

        return Hash::check($code, $this->otp_code_hash);
    }

    public function clearOtp(): void
    {
        $this->forceFill([
            'otp_code_hash'  => null,
            'otp_expires_at' => null,
            'otp_attempts'   => 0,
        ])->save();
    }

    /**
     * Ganti password / matikan 2FA harus lewat kode OTP (bukan "password saat
     * ini") kalau klien memilihnya, atau kalau password akunnya acak dan tidak
     * pernah diketahui klien (akun Google).
     */
    public function requiresOtpForSensitive(): bool
    {
        return (bool) $this->password_otp_enabled || ! $this->passwordKnownToUser();
    }

    /**
     * false hanya untuk akun yang passwordnya acak dan belum pernah diatur
     * klien (login Google). Nilai kosong dianggap true (akun biasa).
     */
    public function passwordKnownToUser(): bool
    {
        return $this->password_set_by_user !== false;
    }

    /**
     * Mulai proses ganti email: simpan alamat baru + hash kode, kembalikan
     * kode mentah untuk dikirim ke alamat BARU. Email lama tetap dipakai
     * sampai kode dikonfirmasi.
     */
    public function startEmailChange(string $newEmail): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'pending_email' => $newEmail,
            'pending_email_code_hash' => Hash::make($code),
            'pending_email_expires_at' => now()->addMinutes(30),
            'pending_email_attempts' => 0,
        ])->save();

        return $code;
    }

    public function pendingEmailCodeIsValid(string $code): bool
    {
        if (! $this->pending_email || ! $this->pending_email_code_hash || ! $this->pending_email_expires_at) {
            return false;
        }

        if ($this->pending_email_expires_at->isPast()) {
            return false;
        }

        return Hash::check($code, $this->pending_email_code_hash);
    }

    public function clearPendingEmail(): void
    {
        $this->forceFill([
            'pending_email' => null,
            'pending_email_code_hash' => null,
            'pending_email_expires_at' => null,
            'pending_email_attempts' => 0,
        ])->save();
    }
}
