<?php

namespace App\Support;

use App\Models\Coupon;
use Illuminate\Support\Collection;

/**
 * Promo per-produk untuk kartu paket hosting/VPS (beranda, /hosting, /vps).
 *
 * Kupon yang dipakai: publik, aktif, dalam rentang tanggal, dan menyasar
 * produk itu (langsung, lewat kategorinya, atau kupon "semua produk").
 * Harga dasar = harga mulai dari (starting_price); bila ada beberapa
 * kupon, dipakai yang harga akhirnya paling murah. Produk yang ditagih
 * per jam (deposit) dilewati karena tidak punya harga siklus tetap.
 * Kupon khusus TLD (domain) tidak berlaku untuk paket.
 *
 * Kupon tetap harus dimasukkan pelanggan di checkout -- ini hanya tampilan.
 */
class ProductPromos
{
    /**
     * @param  Collection<int, \App\Models\Product>  $products
     * @return array<int, array{code: string, label: string, discount: float, before: float, after: float, ends: ?string}>
     */
    public static function for(Collection $products): array
    {
        $found = [];

        if ($products->isEmpty()) {
            return $found;
        }

        try {
            $coupons = Coupon::publicPromo()
                ->with(['products:products.id', 'categories:product_groups.id'])
                ->get();

            foreach ($coupons as $coupon) {
                $productIds  = $coupon->products->pluck('id')->all();
                $categoryIds = $coupon->categories->pluck('id')->all();

                // Kupon "tertentu" yang hanya menyasar TLD tidak untuk paket.
                if ($coupon->applies_to === 'specific' && $productIds === [] && $categoryIds === []) {
                    continue;
                }

                foreach ($products as $product) {
                    if ($product->isDepositBilled()) {
                        continue;
                    }

                    if ($coupon->applies_to === 'specific'
                        && ! in_array($product->id, $productIds, true)
                        && ! in_array($product->product_category_id, $categoryIds, true)) {
                        continue;
                    }

                    $before = (float) $product->starting_price;

                    if ($before <= 0 || $before < (float) $coupon->min_order) {
                        continue;
                    }

                    $discount = $coupon->calculateDiscount($before);

                    if ($discount <= 0) {
                        continue;
                    }

                    $after = $before - $discount;

                    if (isset($found[$product->id]) && $found[$product->id]['after'] <= $after) {
                        continue;
                    }

                    $found[$product->id] = [
                        'code'     => $coupon->code,
                        'label'    => $coupon->value_label,
                        'discount' => $discount,
                        'before'   => $before,
                        'after'    => $after,
                        'ends'     => $coupon->expires_at?->format('d M Y'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Promo hanyalah pemanis; halaman tidak boleh gagal karenanya.
            report($e);

            return [];
        }

        return $found;
    }
}
