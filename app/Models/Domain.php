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
    public function renewalAmount(): float
    {
        return $this->tld ? $this->tld->priceForYears(1, 'renew') : (float) $this->price;
    }

    /**
     * Buat invoice perpanjangan untuk domain ini. Sama seperti
     * HostingAccount::createRenewalInvoice() — dipakai baik oleh
     * perintah terjadwal maupun tombol "Perpanjang Sekarang" klien.
     */
    public function createRenewalInvoice(): \App\Models\Invoice
    {
        $amount = $this->renewalAmount();

        $invoice = \App\Models\Invoice::create([
            'client_id' => $this->client_id,
            'amount' => $amount,
            'tax' => 0,
            'discount' => 0,
            'status' => 'unpaid',
            'issue_date' => now(),
            'due_date' => $this->expiry_date ?: now()->addDays(7),
        ]);

        \App\Models\InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => "Perpanjangan Domain — {$this->domain_name} (1 tahun)",
            'amount' => $amount,
        ]);

        $this->update(['renewal_invoice_id' => $invoice->id]);

        return $invoice;
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->diffInDays(now(), false) > -30 && $this->expiry_date->isFuture();
    }
}
