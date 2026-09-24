<?php

namespace App\Services\Billing;

use App\Models\Client;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Titik masuk tunggal untuk validasi & perhitungan kupon.
 *
 * Rumus & aturan kupon sendiri tetap hidup di Coupon::validateFor(),
 * Coupon::eligibleSubtotal(), dan Coupon::calculateDiscount() (fat model
 * yang sudah teruji, dipakai CheckoutController) — kelas ini tidak
 * menduplikasinya, hanya menyediakan satu pintu masuk bergaya service yang
 * dipanggil dari controller, sesuai lapisan Services/Billing pada
 * blueprint, dan tempat menambahkan aturan lintas-kupon di masa depan
 * (mis. maksimum satu kupon aktif per klien) tanpa menyentuh model.
 */
class CouponService
{
    public function findByCode(string $code): ?Coupon
    {
        return Coupon::where('code', strtoupper(trim($code)))->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    public function eligibleSubtotal(Coupon $coupon, array $cartItems): float
    {
        return $coupon->eligibleSubtotal($cartItems);
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    public function discountFor(?Coupon $coupon, array $cartItems): float
    {
        if (! $coupon) {
            return 0.0;
        }

        return $coupon->calculateDiscount($this->eligibleSubtotal($coupon, $cartItems));
    }

    /**
     * Mengembalikan pesan error kalau kupon tidak valid dipakai klien untuk
     * isi keranjang saat ini, atau null kalau valid.
     *
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    public function validationError(Coupon $coupon, Client $client, array $cartItems): ?string
    {
        return $coupon->validateFor($client, $this->eligibleSubtotal($coupon, $cartItems));
    }

    public function reserveForInvoice(Coupon $coupon, Client $client, Invoice $invoice, float $discount): CouponUsage
    {
        return DB::transaction(function () use ($coupon, $client, $invoice, $discount) {
            $existing = CouponUsage::where('invoice_id', $invoice->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            // Lock kupon agar dua checkout paralel tidak sama-sama lolos
            // usage_limit / usage_limit_per_client sebelum membuat reservation.
            $lockedCoupon = Coupon::whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            $reservedTotal = $lockedCoupon->usages()->where('status', 'reserved')->count();
            $effectiveUsage = (int) $lockedCoupon->usage_count + $reservedTotal;

            if ($lockedCoupon->usage_limit !== null && $effectiveUsage >= $lockedCoupon->usage_limit) {
                throw new \RuntimeException('Kupon sudah mencapai batas pemakaian.');
            }

            $usedByClient = $lockedCoupon->usages()
                ->where('client_id', $client->id)
                ->whereIn('status', ['reserved', 'consumed'])
                ->count();

            if ($usedByClient >= $lockedCoupon->usage_limit_per_client) {
                throw new \RuntimeException('Anda sudah memakai kupon ini sebelumnya.');
            }

            return CouponUsage::create([
                'coupon_id' => $lockedCoupon->id,
                'client_id' => $client->id,
                'invoice_id' => $invoice->id,
                'discount_amount' => $discount,
                'status' => 'reserved',
            ]);
        });
    }

    public function consumeForInvoice(Invoice $invoice): void
    {
        if (! $invoice->coupon_id) return;
        DB::transaction(function () use ($invoice) {
            $usage = CouponUsage::where('invoice_id', $invoice->id)->lockForUpdate()->first();
            if (! $usage || $usage->status !== 'reserved') return;
            $usage->update(['status' => 'consumed']);
            Coupon::whereKey($usage->coupon_id)->lockForUpdate()->first()?->increment('usage_count');
        });
    }

    public function releaseForInvoice(Invoice $invoice): void
    {
        if (! $invoice->coupon_id) return;
        CouponUsage::where('invoice_id', $invoice->id)->where('status', 'reserved')->update(['status' => 'released']);
    }
}


