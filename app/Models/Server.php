<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'hostname', 'ns1', 'ns2', 'port', 'panel', 'api_username', 'api_token',
        'verify_ssl', 'max_accounts', 'is_active', 'last_checked_at', 'last_check_status',
        'price_per_vcpu_hour', 'price_per_ram_gb_hour', 'price_per_storage_gb_hour',
        'price_per_backup_gb_hour', 'price_per_snapshot_gb_hour', 'price_windows_license_per_vcpu_hour',
        'pricing_mode', 'markup_percent', 'cost_cache', 'cost_cached_at',
    ];

    protected $hidden = [
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'api_token'      => 'encrypted',
            'cost_cache'     => 'array',
            'cost_cached_at' => 'datetime', // otomatis dienkripsi/didekripsi Laravel pakai APP_KEY
            'verify_ssl'     => 'boolean',
            'is_active'      => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function hostingAccounts(): HasMany
    {
        return $this->hasMany(HostingAccount::class);
    }

    public function getAccountsCountAttribute(): int
    {
        return $this->hostingAccounts()->count();
    }

    /**
     * Base URL API sesuai jenis panel.
     * cPanel/WHM  -> https://host:2087
     * DirectAdmin -> https://host:2222
     * Plesk       -> https://host:8443
     */
    public function getApiBaseUrlAttribute(): string
    {
        return "https://{$this->hostname}:{$this->port}";
    }
}
