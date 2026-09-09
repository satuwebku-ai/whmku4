<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'type', 'value', 'min_order', 'max_discount', 'applies_to',
        'usage_limit', 'usage_count', 'usage_limit_per_client',
        'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon) {
            $coupon->code = strtoupper(trim($coupon->code));
        });
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'coupon_product_category');
    }

    /**
     * Jumlah dari isi keranjang yang BENAR-BENAR jadi sasaran kupon ini
     * — bukan seluruh subtotal keranjang. Kupon "all" tetap menghitung
     * semuanya (perilaku lama, tidak berubah); kupon "specific" cuma
     * menjumlahkan item produk yang cocok (lewat produk itu sendiri ATAU
     * kategorinya), dan mengabaikan sisanya — misal registrasi domain di
     * keranjang yang sama tetap dihitung penuh, tidak ikut didiskon.
     *
     * @param  array<int, array<string, mixed>>  $cartItems  hasil CartService::items()
     */
    public function eligibleSubtotal(array $cartItems): float
    {
        if ($this->applies_to === 'all') {
            return array_sum(array_column($cartItems, 'price'));
        }

        $productIds = $this->products()->pluck('products.id')->all();
        $categoryIds = $this->categories()->pluck('product_categories.id')->all();

        $eligible = 0.0;

        foreach ($cartItems as $item) {
            if (($item['type'] ?? null) !== 'product') {
                continue; // registrasi domain berdiri sendiri tidak pernah ikut didiskon kupon produk
            }

            $productId = $item['product_id'] ?? null;
            $categoryId = $productId ? \App\Models\Product::find($productId)?->product_category_id : null;

            $matches = ($productId && in_array($productId, $productIds, true))
                || ($categoryId && in_array($categoryId, $categoryIds, true));

            if ($matches) {
                $eligible += (float) $item['price'];
            }
        }

        return $eligible;
    }

    /**
     * Periksa apakah kupon ini boleh dipakai klien tertentu untuk subtotal
     * tertentu. Mengembalikan pesan error kalau tidak valid, atau null
     * kalau valid — dipilih daripada exception supaya mudah ditampilkan
     * langsung sebagai pesan form.
     *
     * PENTING: $subtotal di sini harus subtotal yang SUDAH DISARING lewat
     * eligibleSubtotal() (bukan subtotal keranjang penuh) — supaya
     * ambang "min_order" dicek terhadap produk yang benar-benar kena
     * diskon, bukan total belanja yang mungkin sebagian besar tidak
     * tersentuh kupon ini sama sekali.
     */
    public function validateFor(Client $client, float $subtotal): ?string
    {
        if (! $this->is_active) {
            return 'Kupon ini sudah tidak aktif.';
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'Kupon ini belum bisa dipakai.';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'Kupon ini sudah kedaluwarsa.';
        }

        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return 'Kupon ini sudah mencapai batas pemakaian.';
        }

        if ($this->applies_to === 'specific' && $subtotal <= 0) {
            return 'Kupon ini tidak berlaku untuk produk yang ada di keranjang Anda.';
        }

        if ($subtotal < (float) $this->min_order) {
            return 'Minimal transaksi untuk kupon ini adalah Rp ' . number_format((float) $this->min_order, 0, ',', '.') . '.';
        }

        $usedByClient = $this->invoices()
            ->where('client_id', $client->id)
            ->whereIn('status', ['paid', 'unpaid', 'overdue'])
            ->count();

        if ($usedByClient >= $this->usage_limit_per_client) {
            return 'Anda sudah memakai kupon ini sebelumnya.';
        }

        return null;
    }

    /**
     * Hitung nominal potongan untuk subtotal tertentu.
     */
    public function calculateDiscount(float $subtotal): float
    {
        $discount = $this->type === 'percent'
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        // Diskon tidak pernah melebihi subtotal itu sendiri.
        return round(min($discount, $subtotal), 2);
    }

    public function getValueLabelAttribute(): string
    {
        return $this->type === 'percent'
            ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.') . '%'
            : 'Rp ' . number_format((float) $this->value, 0, ',', '.');
    }
}
