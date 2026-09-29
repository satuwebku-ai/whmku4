<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomPremiumDomain extends Model
{
    protected $fillable = [
        'domain_name', 'label', 'extension', 'characters', 'age_label', 'years',
        'cost_price', 'sell_price', 'renew_price', 'is_active', 'note', 'import_batch',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'renew_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Pecah "abner.id" menjadi [label, extension] => ['abner', '.id'].
     * Ekstensi bertingkat ikut ("toko.co.id" => ['toko', '.co.id']).
     */
    public static function splitDomain(string $domain): ?array
    {
        $domain = strtolower(trim($domain));

        if (! preg_match('/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)\.([a-z0-9-]+(?:\.[a-z0-9-]+)*)$/', $domain, $m)) {
            return null;
        }

        return [$m[1], '.' . $m[2]];
    }

    /** Hanya yang aktif, sudah punya harga jual, dan namanya belum dipesan/aktif sebagai domain. */
    public function scopeForSale($query)
    {
        return $query->where('is_active', true)
            ->where('sell_price', '>', 0)
            ->whereNotIn('domain_name', Domain::query()->whereIn('status', ['pending', 'active'])->select('domain_name'));
    }
}
