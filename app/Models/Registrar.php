<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registrar extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'provider', 'api_url', 'api_username', 'api_key', 'username', 'client_ip',
        'default_ns1', 'default_ns2', 'documents_email', 'whois_privacy_price',
        'sandbox', 'is_active', 'is_default', 'last_checked_at', 'last_check_status',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'sandbox' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function tlds(): HasMany
    {
        return $this->hasMany(Tld::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Base URL API sesuai provider.
     *
     * - Namecheap: URL tetap, hanya beda sandbox/production
     * - Liqu.id & lainnya: URL berbeda tiap akun (software di-deploy
     *   per-registrar), jadi diambil dari kolom api_url
     */
    public function getApiBaseUrlAttribute(): string
    {
        if ($this->provider === 'namecheap') {
            return $this->sandbox
                ? 'https://api.sandbox.namecheap.com/xml.response'
                : 'https://api.namecheap.com/xml.response';
        }

        return rtrim((string) $this->api_url, '/');
    }

    /**
     * Alias supaya lebih jelas saat dipakai provider yang memakai istilah
     * "Reseller ID" (Liqu.id) alih-alih "API User" (Namecheap).
     * Keduanya disimpan di kolom yang sama: api_username.
     */
    public function getResellerIdAttribute(): ?string
    {
        return $this->api_username;
    }
}
