<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'hostname', 'ns1', 'ns2', 'port', 'panel', 'vps_provider', 'api_username', 'api_token', 'server_group_id',
        'verify_ssl', 'max_accounts', 'is_active', 'last_checked_at', 'last_check_status',
        'price_per_vcpu_hour', 'price_per_ram_gb_hour', 'price_per_storage_gb_hour',
        'price_per_backup_gb_hour', 'price_per_snapshot_gb_hour', 'price_windows_license_per_vcpu_hour',
        'pricing_mode', 'markup_percent', 'cost_cache', 'cost_cached_at', 'cost_fx_rate',
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
            'cost_fx_rate'   => 'decimal:4',
            'verify_ssl'     => 'boolean',
            'is_active'      => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function hostingAccounts(): HasMany
    {
        return $this->hasMany(HostingAccount::class);
    }

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ServerGroup::class, 'server_group_id');
    }

    public function getAccountsCountAttribute(): int
    {
        return $this->hostingAccounts()->count();
    }

    /**
     * Server cloud/VPS = jenis panel "vps" + provider di kolom vps_provider
     * (idcloudhost, digitalocean, dst -- daftar lengkapnya di
     * config/vps_providers.php). Satu-satunya definisi "server VPS": semua
     * query & pengecekan lain memakai scopeCloud() / isCloud() di sini.
     */
    public function scopeCloud(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('panel', 'vps')->orWhereNotNull('vps_provider'));
    }

    public function isCloud(): bool
    {
        return $this->panel === 'vps' || filled($this->vps_provider);
    }

    /** Kunci provider VPS (mis. "idcloudhost"), atau null kalau bukan server VPS. */
    public function vpsDriver(): ?string
    {
        return filled($this->vps_provider) ? $this->vps_provider : null;
    }

    /**
     * Cara provider menghitung harga modal: 'component' (per vCPU/RAM/disk,
     * spek bebas) atau 'size' (paket tetap milik provider). Lihat
     * config/vps_providers.php.
     */
    public function costModel(): string
    {
        return (string) ($this->cost_cache['model'] ?? config("vps_providers.{$this->vpsDriver()}.cost_model", 'component'));
    }

    /** Mata uang harga modal dari provider (IDR, USD, ...). */
    public function costCurrency(): string
    {
        return strtoupper((string) ($this->cost_cache['currency'] ?? config("vps_providers.{$this->vpsDriver()}.currency", 'IDR')));
    }

    /**
     * Pengali harga modal ke Rupiah: 1 untuk provider ber-IDR, kurs yang
     * diisi admin untuk mata uang lain, dan 0 kalau kurs belum diisi
     * (harga modal dianggap belum bisa dipakai -- tidak menebak kurs).
     */
    public function costFxRate(): float
    {
        return $this->costCurrency() === 'IDR' ? 1.0 : (float) ($this->cost_fx_rate ?? 0);
    }

    /** Nama tampilan provider VPS, mis. "IDCloudHost". */
    public function vpsLabel(): string
    {
        $driver = $this->vpsDriver();

        return $driver ? (string) config("vps_providers.{$driver}.label", ucfirst($driver)) : '';
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
