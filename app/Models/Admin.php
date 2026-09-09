<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'avatar',
        'role',
        'permissions',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'two_factor_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active'          => 'boolean',
            'two_factor_enabled' => 'boolean',
            'last_login_at'      => 'datetime',
            'otp_expires_at'     => 'datetime',
            'password'           => 'hashed',
            'permissions'        => 'array',
        ];
    }

    /**
     * Buat OTP 6 digit, simpan hash-nya, kembalikan kode aslinya
     * untuk dikirim lewat email. Kode mentah tidak pernah disimpan.
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
     * Admin tidak punya nomor sendiri — notifikasi WhatsApp untuk admin
     * dikirim ke satu nomor yang diatur di Pengaturan → Notifikasi.
     */
    /**
     * Peran dasar. Sejak ditambahkan sistem izin per-modul (lihat MODULES
     * & hasModule()), peran ini terutama berfungsi sebagai:
     *  1. "superadmin" — selalu akses penuh, satu-satunya yang boleh
     *     mengelola admin lain & mengatur izin modul mereka.
     *  2. "admin"/"staff" — cuma label + nilai bawaan checklist modul
     *     saat admin baru dibuat. Akses SEBENARNYA ditentukan kolom
     *     `permissions`, yang bisa dikustom manual per admin oleh
     *     superadmin lewat Admin & Akses -- bukan cuma dua level tetap.
     */
    public const ROLES = [
        'superadmin' => 'Superadmin — akses penuh, termasuk mengelola admin lain & mengatur izin modul',
        'admin'      => 'Admin — kelola modul yang diizinkan superadmin (bawaan: semua kecuali Sistem)',
        'staff'      => 'Staff — kelola modul yang diizinkan superadmin (bawaan: Layanan & Dukungan saja)',
    ];

    /**
     * Modul-modul yang bisa dibuka/tutup manual per admin oleh superadmin.
     * Kuncinya dipakai di middleware `module:xxx` pada routes/admin.php dan
     * di filter menu sidebar (resources/views/layouts/admin.blade.php) —
     * kalau menambah modul baru, dua tempat itu juga perlu disesuaikan.
     */
    public const MODULES = [
        'sales'          => 'Penjualan — Produk, Order, Kupon',
        'billing'        => 'Billing — Invoice, Pembayaran, Payment Gateway',
        'services'       => 'Layanan — Klien, Hosting Account, Domain, Verifikasi Berkas',
        'infrastructure' => 'Infrastruktur — Server, VPS, Registrar, Backup, Konsol Web',
        'support'        => 'Dukungan — Live Chat, Tiket Support',
        'content'        => 'Konten — Halaman, Pengumuman, Banner, Template Notifikasi',
        'system'         => 'Sistem — Pengaturan, Cron, Log Aktivitas, Broadcast Promo',
    ];

    /**
     * Modul bawaan kalau admin belum pernah diatur manual izinnya
     * (kolom `permissions` masih NULL) — dipakai juga sebagai nilai
     * awal centang di form tambah admin saat peran dipilih.
     *
     * Superadmin tidak perlu masuk sini — selalu lolos semua modul,
     * lihat hasModule().
     */
    public const ROLE_DEFAULT_MODULES = [
        'admin' => ['sales', 'billing', 'services', 'infrastructure', 'support', 'content'],
        'staff' => ['services', 'support'],
    ];

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * Apakah admin ini boleh masuk & bekerja penuh di modul tertentu?
     *
     * Superadmin selalu lolos. Selain itu, dicek dari daftar `permissions`
     * yang diatur manual superadmin lewat form Admin & Akses; kalau belum
     * pernah diatur (NULL), dipakai daftar bawaan sesuai peran. Array
     * kosong `[]` berarti sengaja dikunci total dari semua modul.
     */
    public function hasModule(string $module): bool
    {
        if ($this->role === 'superadmin') {
            return true;
        }

        $allowed = is_null($this->permissions)
            ? (self::ROLE_DEFAULT_MODULES[$this->role] ?? [])
            : $this->permissions;

        return in_array($module, $allowed, true);
    }

    /**
     * Modul aktual yang berlaku untuk admin ini sekarang (hasil resolusi
     * permissions custom / bawaan peran) — dipakai form edit supaya
     * checkbox tercentang sesuai kondisi nyata, bukan cuma nilai mentah
     * kolom `permissions` yang bisa saja masih NULL.
     */
    public function effectiveModules(): array
    {
        if ($this->role === 'superadmin') {
            return array_keys(self::MODULES);
        }

        return is_null($this->permissions)
            ? (self::ROLE_DEFAULT_MODULES[$this->role] ?? [])
            : $this->permissions;
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'superadmin' => 'Superadmin',
            'staff' => 'Staff',
            default => 'Admin',
        };
    }

    public function routeNotificationForWhatsApp(): ?string
    {
        return \App\Models\Setting::get('wa_admin_number');
    }

    /**
     * Sama seperti routeNotificationForWhatsApp() di atas -- admin tidak
     * punya kolom nomor pribadi di sistem ini, jadi SMS ke admin
     * ditujukan ke SATU nomor bersama yang diatur admin di Pengaturan
     * (biasanya nomor pemilik/penanggung jawab bisnis), bukan per-akun.
     */
    public function routeNotificationForSms(): ?string
    {
        return \App\Models\Setting::get('sms_admin_number');
    }

    public function pushSubscriptions(): MorphMany
    {
        return $this->morphMany(PushSubscription::class, 'subscribable');
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }

        // Fallback: avatar inisial via ui-avatars
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=6366F1&color=fff';
    }
}
