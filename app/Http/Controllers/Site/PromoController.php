<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\PromoBanner;
use Illuminate\View\View;

/**
 * Halaman Promo publik. Tidak punya tabel sendiri: memakai kupon yang admin
 * tandai "tampilkan di halaman Promo" dan banner ber-halaman "promo".
 * Kode tetap dimasukkan pelanggan di checkout; halaman ini tidak pernah
 * menerapkan diskon otomatis.
 */
class PromoController extends Controller
{
    public function index(): View
    {
        $promos = Coupon::publicPromo()
            ->orderByRaw('expires_at is null')   // yang segera berakhir tampil dulu
            ->orderBy('expires_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Coupon $coupon) => $this->present($coupon));

        return view('public.promo', [
            'promos'  => $promos,
            'banners' => PromoBanner::live()->forPage('promo')->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Coupon $coupon): array
    {
        $tlds = $coupon->targetsTlds()
            ? $coupon->tlds()->where('is_active', true)->values()
            : collect();

        $tldRows = $tlds->map(function ($tld) use ($coupon) {
            $years  = max((int) $tld->min_years, 1);
            $before = $tld->priceForYears($years);
            // Harga contoh setelah kode, hanya bila syarat min. transaksi terpenuhi.
            $after  = $before >= (float) $coupon->min_order
                ? $before - $coupon->calculateDiscount($before)
                : null;

            return [
                'extension'      => $tld->extension,
                'years'          => $years,
                'before'         => $before,
                'after'          => $after,
                'searchable'     => (bool) $tld->show_in_search,
            ];
        })->all();

        $scoped = $coupon->applies_to === 'specific';

        return [
            'coupon'     => $coupon,
            'tlds'       => $tldRows,
            'products'   => $scoped ? $coupon->products()->orderBy('name')->pluck('name')->all() : [],
            'categories' => $scoped ? $coupon->categories()->orderBy('name')->pluck('name')->all() : [],
            'all'        => ! $scoped,
            // Ekstensi yang bisa langsung dicentang di Cek Domain.
            'searchExtensions' => collect($tldRows)->where('searchable', true)->pluck('extension')->all(),
        ];
    }
}
