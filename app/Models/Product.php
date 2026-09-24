<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_category_id', 'name', 'slug', 'tagline', 'description', 'features',
        'price_monthly', 'price_quarterly', 'price_semi_annually', 'price_annually', 'price_custom', 'setup_fee',
        'custom_cycle_days', 'domain_option', 'server_id', 'panel_package', 'billing_mode',
        'pricing_mode', 'markup_percent', 'price_per_vcpu_hour', 'price_per_ram_gb_hour',
        'price_per_storage_gb_hour', 'price_per_backup_gb_hour', 'price_per_snapshot_gb_hour',
        'price_windows_license_per_vcpu_hour',
        'is_active', 'is_featured', 'stock', 'reserved_stock', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price_monthly' => 'decimal:2',
            'price_quarterly' => 'decimal:2',
            'price_semi_annually' => 'decimal:2',
            'price_annually' => 'decimal:2',
            'price_custom' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'markup_percent' => 'decimal:2',
            'price_per_vcpu_hour' => 'decimal:6',
            'price_per_ram_gb_hour' => 'decimal:6',
            'price_per_storage_gb_hour' => 'decimal:6',
            'price_per_backup_gb_hour' => 'decimal:6',
            'price_per_snapshot_gb_hour' => 'decimal:6',
            'price_windows_license_per_vcpu_hour' => 'decimal:6',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'reserved_stock' => 'integer',
        ];
    }

    /**
     * Label siklus tagihan dalam Bahasa Indonesia — dipakai berulang di
     * halaman katalog, form produk, dan keranjang.
     */
    public const CYCLES = [
        'monthly' => 'Bulanan',
        'quarterly' => '3 Bulan',
        'semi_annually' => '6 Bulan',
        'annually' => 'Tahunan',
        'custom' => 'Custom',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (blank($product->slug)) {
                $product->slug = static::uniqueSlug($product->name, $product->id);
            } else {
                $product->slug = Str::slug($product->slug);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_category_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function hostingAccounts(): HasMany
    {
        return $this->hasMany(HostingAccount::class);
    }

    public function pricings(): HasMany
    {
        return $this->hasMany(ProductPricing::class);
    }

    public function optionGroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Siklus tagihan yang benar-benar dijual untuk produk ini
     * (harga-nya diisi, tidak null).
     *
     * @return array<string, float>
     */
    public function availableCycles(): array
    {
        $cycles = [];

        foreach (self::CYCLES as $key => $label) {
            $price = $this->{"price_{$key}"};

            if ($price !== null) {
                $cycles[$key] = (float) $price;
            }
        }

        return $cycles;
    }

    public function priceForCycle(string $cycle): ?float
    {
        $value = $this->{"price_{$cycle}"} ?? null;

        return $value !== null ? (float) $value : null;
    }

    /**
     * Label yang ditampilkan ke klien — untuk siklus custom, tampilkan
     * jumlah harinya langsung ("Custom (45 hari)") supaya jelas, bukan
     * cuma kata "Custom" tanpa keterangan.
     */
    public function cycleLabel(string $cycle): string
    {
        if ($cycle === 'custom' && $this->custom_cycle_days) {
            return "Custom ({$this->custom_cycle_days} hari)";
        }

        return self::CYCLES[$cycle] ?? $cycle;
    }

    /**
     * Harga terendah untuk ditampilkan di kartu katalog, mis. "mulai dari".
     */
    public function getStartingPriceAttribute(): ?float
    {
        $cycles = $this->availableCycles();

        return $cycles ? min($cycles) : null;
    }

    /**
     * true kalau produk ini ditagih per jam dari saldo (Pay As You Grow),
     * bukan harga siklus tetap. Dipakai storefront publik untuk kasih
     * badge -- supaya calon pembeli tahu cara tagihnya SEBELUM checkout,
     * bukan baru sadar dari invoice pertama yang isinya Rp 0.
     */
    public function isDepositBilled(): bool
    {
        return $this->billing_mode === 'deposit';
    }

    /**
     * true kalau produk ini punya kartu harga per-jam SENDIRI: mode markup,
     * atau minimal satu tarif komponen terisi. Kalau false, tarif jatuh ke
     * kartu harga server (HourlyRateCalculator::effectiveRates()).
     */
    public function hasHourlyRateCard(): bool
    {
        if ($this->pricing_mode === 'markup') {
            return true;
        }

        return collect([
            'price_per_vcpu_hour', 'price_per_ram_gb_hour', 'price_per_storage_gb_hour',
            'price_per_backup_gb_hour', 'price_per_snapshot_gb_hour', 'price_windows_license_per_vcpu_hour',
        ])->contains(fn ($column) => (float) ($this->{$column} ?? 0) > 0);
    }

    /**
     * Tarif per jam (Rp) produk VPS deposit ini untuk ditampilkan di
     * katalog -- dihitung HourlyRateCalculator yang sama dengan penagihan.
     * null kalau bukan produk VPS bertarif (belum ada server/spek/tarif).
     */
    public function estimatedHourlyRate(): ?float
    {
        $spec = json_decode((string) $this->panel_package, true);

        if (! $this->server_id || ! is_array($spec) || ! isset($spec['vcpu'])) {
            return null;
        }

        $this->loadMissing('server');

        $rate = $this->server ? \App\Services\Billing\HourlyRateCalculator::calculate($this->server, $spec, $this) : 0.0;

        return $rate > 0 ? $rate : null;
    }

    /**
     * SATU-SATUNYA definisi "produk VPS" -- dipakai di mana pun produk
     * perlu dikelompokkan VPS vs hosting biasa (beranda, katalog, admin).
     * Sumber utamanya kategori (product_groups.type = 'vps'), yang juga
     * menentukan URL publik (ProductGroup::urlSection()) -- jadi
     * kelompoknya konsisten dengan tautan yang dilihat pengunjung. Server
     * cloud dicek juga sebagai jaring pengaman untuk produk lama yang
     * kategorinya belum diisi type VPS tapi terlanjur dipasang di server
     * cloud (atau sebaliknya).
     *
     * scopeVpsType()/scopeHostingType() adalah versi SQL dari cek yang
     * sama -- pakai scope itu (bukan memfilter koleksi dengan method ini
     * satu per satu) supaya tidak N+1 query dan supaya LIMIT/paginate di
     * query jalan pada himpunan yang benar.
     */
    public function isVpsProduct(): bool
    {
        if ($this->relationLoaded('category') ? $this->category?->type === 'vps' : $this->category()->where('type', 'vps')->exists()) {
            return true;
        }

        return (bool) $this->server_id && Server::cloud()->whereKey($this->server_id)->exists();
    }

    public function scopeVpsType(Builder $query): Builder
    {
        $cloudServerIds = Server::cloud()->pluck('id');

        return $query->where(fn (Builder $q) => $q->whereHas('category', fn (Builder $c) => $c->where('type', 'vps'))
            ->orWhereIn('server_id', $cloudServerIds));
    }

    public function scopeHostingType(Builder $query): Builder
    {
        $cloudServerIds = Server::cloud()->pluck('id');

        return $query->whereDoesntHave('category', fn (Builder $c) => $c->where('type', 'vps'))
            ->where(fn (Builder $q) => $q->whereNotIn('server_id', $cloudServerIds)->orWhereNull('server_id'));
    }

    public function requiresDomain(): bool
    {
        return $this->domain_option === 'required';
    }

    public function allowsDomain(): bool
    {
        return $this->domain_option !== 'none';
    }

    public function isInStock(): bool
    {
        return $this->stock === null || ((int) $this->stock - (int) ($this->reserved_stock ?? 0)) > 0;
    }

    public function pricingForClientCycle(?int $clientGroupId, string $cycle): ?array
    {
        if ($clientGroupId) {
            $special = $this->pricings()->where('client_group_id', $clientGroupId)->where('billing_cycle', $cycle)->first();
            if ($special) {
                return ['price' => (float) $special->price, 'setup_fee' => (float) $special->setup_fee];
            }
        }

        $price = $this->priceForCycle($cycle);
        return $price === null ? null : ['price' => $price, 'setup_fee' => (float) $this->setup_fee];
    }
}
