<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class Client extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'address',
        'city', 'state', 'postal_code', 'country', 'password', 'status', 'internal_notes',
        'email_verified_at', 'last_login_at', 'last_login_ip',
        'whatsapp_number', 'notify_promo', 'notify_whatsapp', 'notify_sms',
        'google_id', 'avatar', 'balance', 'two_factor_enabled',
    ];

    protected $hidden = ['password', 'remember_token', 'internal_notes'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
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
    public function adjustBalance(float $amount, string $type, string $description, ?\App\Models\Invoice $invoice = null, ?\App\Models\Admin $admin = null): \App\Models\ClientBalanceLog
    {
        $newBalance = round((float) $this->balance + $amount, 2);

        $this->update(['balance' => $newBalance]);

        return \App\Models\ClientBalanceLog::create([
            'client_id' => $this->id,
            'amount' => $amount,
            'type' => $type,
            'description' => $description,
            'invoice_id' => $invoice?->id,
            'admin_id' => $admin?->id,
            'balance_after' => $newBalance,
        ]);
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
        return $this->hasMany(ClientBalanceLog::class);
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

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', trim($this->name));
        $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));

        return $initials ?: 'NA';
    }

    public function getAvatarUrlAttribute(): string
    {
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=6366F1&color=fff';
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
}
