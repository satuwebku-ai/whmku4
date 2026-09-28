<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Jejak minat domain dari client yang sudah login.
 *
 * Pengunjung anonim sengaja tidak pernah ditulis ke tabel ini. Satu baris
 * mewakili satu kejadian: pencarian, masuk keranjang, atau checkout.
 */
class DomainInterest extends Model
{
    use HasFactory;

    public const SEARCH = 'search';
    public const CART = 'cart';
    public const CHECKOUT = 'checkout';

    protected $fillable = [
        'client_id',
        'event_type',
        'domain_name',
        'tld_id',
        'tld_premium_id',
        'years',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'years' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tld(): BelongsTo
    {
        return $this->belongsTo(Tld::class);
    }

    public function tldPremium(): BelongsTo
    {
        return $this->belongsTo(TldPremium::class);
    }

    /**
     * Simpan domain yang dicari oleh client yang sedang login.
     *
     * Tidak menyimpan IP, session ID, atau identitas pengunjung anonim.
     */
    public static function recordSearch(array $domains, array $metadata = []): void
    {
        $client = Auth::guard('client')->user();

        if (! $client) {
            return;
        }

        $now = now();
        $rows = collect($domains)
            ->map(fn ($domain) => strtolower(trim((string) $domain)))
            ->filter()
            ->unique()
            ->map(fn ($domain) => [
                'client_id' => $client->id,
                'event_type' => self::SEARCH,
                'domain_name' => $domain,
                'source' => 'domain_search',
                'metadata' => empty($metadata) ? null : json_encode($metadata),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            static::query()->insert($rows);
        }
    }

    /**
     * Simpan domain yang berhasil masuk keranjang.
     */
    public static function recordCart(
        string $domainName,
        ?int $tldId = null,
        ?int $tldPremiumId = null,
        ?int $years = null,
        string $source = 'cart',
        array $metadata = [],
    ): void {
        static::recordEvent(self::CART, $domainName, $tldId, $tldPremiumId, $years, $source, $metadata);
    }

    /**
     * Tandai domain di keranjang sebagai benar-benar masuk checkout.
     */
    public static function recordCheckout(
        string $domainName,
        ?int $tldId = null,
        ?int $tldPremiumId = null,
        ?int $years = null,
        ?int $invoiceId = null,
        array $metadata = [],
    ): void {
        if ($invoiceId !== null) {
            $metadata['invoice_id'] = $invoiceId;
        }

        static::recordEvent(self::CHECKOUT, $domainName, $tldId, $tldPremiumId, $years, 'checkout', $metadata);
    }

    private static function recordEvent(
        string $eventType,
        string $domainName,
        ?int $tldId,
        ?int $tldPremiumId,
        ?int $years,
        string $source,
        array $metadata,
    ): void {
        $client = Auth::guard('client')->user();

        if (! $client || blank($domainName)) {
            return;
        }

        static::query()->create([
            'client_id' => $client->id,
            'event_type' => $eventType,
            'domain_name' => strtolower(trim($domainName)),
            'tld_id' => $tldId,
            'tld_premium_id' => $tldPremiumId,
            'years' => $years,
            'source' => $source,
            'metadata' => $metadata ?: null,
        ]);
    }
}