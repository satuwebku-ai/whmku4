<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'order_id', 'registrar_id', 'tld_id', 'domain_name',
        'price', 'years', 'status', 'register_date', 'expiry_date',
        'auto_renew', 'whois_privacy', 'nameservers',
        'provision_status', 'provision_message', 'internal_notes',
        'renewal_invoice_id', 'is_transfer', 'transfer_auth_code',
        'eligibility_criteria', 'eligibility_extra', 'documents_verified_at',
        'privacy_invoice_id', 'privacy_expires_at',
        'provisioning_started_at', 'provisioning_finished_at', 'provisioning_attempts', 'provisioning_key',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'register_date' => 'date',
            'expiry_date' => 'date',
            'auto_renew' => 'boolean',
            'whois_privacy' => 'boolean',
            'nameservers' => 'array',
            'is_transfer' => 'boolean',
            'transfer_auth_code' => 'encrypted',
            'documents_verified_at' => 'datetime',
            'privacy_expires_at' => 'date',
            'provisioning_started_at' => 'datetime',
            'provisioning_finished_at' => 'datetime',
            'provisioning_attempts' => 'integer',
        ];
    }

    /**
     * ID Protection dianggap benar-benar aktif hanya kalau flag-nya
     * menyala DAN masa berlakunya belum lewat — masa berlaku ini
     * terpisah dari masa domain (lihat migrasi privacy_expires_at).
     */
    public function hasActivePrivacy(): bool
    {
        return $this->whois_privacy
            && $this->privacy_expires_at
            && $this->privacy_expires_at->isFuture();
    }

    public function privacyDaysLeft(): ?int
    {
        if (! $this->privacy_expires_at) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->privacy_expires_at, false);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\DomainDocument::class);
    }

    public function contacts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DomainContact::class);
    }

    public function privacyInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'privacy_invoice_id');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(Registrar::class);
    }

    public function tld(): BelongsTo
    {
        return $this->belongsTo(Tld::class);
    }

    public function renewalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'renewal_invoice_id');
    }

    /**
     * Nominal perpanjangan satu tahun, mengikuti harga renew TLD saat ini
     * (bukan harga registrasi awal — keduanya sering berbeda).
     */
    public function markProvisioning(string $message = 'Provisioning domain sedang dijalankan.'): void
    {
        $this->increment('provisioning_attempts');
        $this->forceFill([
            'provisioning_started_at' => now(),
            'provisioning_finished_at' => null,
            'provisioning_key' => $this->provisioning_key ?: (string) \Illuminate\Support\Str::uuid(),
            'provision_status' => 'provisioning',
            'provision_message' => $message,
        ])->save();
    }

    public function markProvisioningFinished(string $status, string $message): void
    {
        $this->forceFill([
            'provision_status' => $status,
            'provision_message' => $message,
            'provisioning_finished_at' => now(),
        ])->save();
    }

    public function renewalAmount(): float
    {
        return $this->tld ? $this->tld->priceForYears(1, 'renew') : (float) $this->price;
    }

    /**
     * Perpanjangan domain dibuka maksimal 30 hari sebelum expiry.
     * Batas ini berlaku untuk tombol manual maupun invoice otomatis.
     */
    public function isWithinRenewalWindow(int $daysBefore = 30): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date->lessThanOrEqualTo(now()->addDays($daysBefore));
    }

    /**
     * Buat invoice perpanjangan untuk domain ini. Sama seperti
     * HostingAccount::createRenewalInvoice() — dipakai baik oleh
     * perintah terjadwal maupun tombol "Perpanjang Sekarang" klien.
     */
    public function createRenewalInvoice(): \App\Models\Invoice
    {
        return app(\App\Services\Billing\RenewalInvoiceService::class)->createDomainInvoice($this);
    }


    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->diffInDays(now(), false) > -30 && $this->expiry_date->isFuture();
    }
}
