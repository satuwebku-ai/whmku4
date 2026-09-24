<?php

namespace App\Services\Affiliate;

use App\Exceptions\Affiliate\AffiliateException;
use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateCommissionReversal;
use App\Models\AffiliateConversion;
use App\Models\AffiliateCommissionRule;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung & memoderasi komisi. Urutan prioritas tarif komisi (bab 18
 * blueprint: "Commission percentage/fixed"):
 *   1. Campaign yang dipakai saat klik (kalau ada override)
 *   2. Affiliate itu sendiri (kalau ada override)
 *   3. Default global lewat Settings (affiliate_commission_type/value)
 */
class AffiliateCommissionService
{
    public function __construct(
        private readonly AffiliateWalletService $wallet,
        private readonly AffiliateAuditService $audit,
    ) {}

    public function calculate(AffiliateConversion $conversion): float
    {
        $rate = $this->rateFor($conversion);
        $base = (float) ($conversion->net_paid_amount ?? $conversion->amount);

        return round(max(0, $rate['type'] === 'percentage'
            ? $base * ($rate['value'] / 100)
            : $rate['value']), 2);
    }

    /**
     * Buat baris komisi (status pending) untuk sebuah konversi. Dipanggil
     * AffiliateConversionService segera setelah konversi dibuat.
     */
    public function createForConversion(AffiliateConversion $conversion): AffiliateCommission
    {
        $rate = $this->rateFor($conversion);
        $base = round((float) ($conversion->net_paid_amount ?? $conversion->amount), 2);
        $gross = $this->calculate($conversion);
        $taxEnabled = filter_var(Setting::get('affiliate_tax_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $taxRate = $taxEnabled ? (float) Setting::get('affiliate_tax_rate', 0) : 0;
        $tax = round($gross * ($taxRate / 100), 2);

        return AffiliateCommission::create([
            'affiliate_id' => $conversion->affiliate_id,
            'affiliate_conversion_id' => $conversion->id,
            'amount' => $gross,
            'commission_type' => $rate['type'],
            'commission_rate' => $rate['value'],
            'commission_base' => $base,
            'tax_amount' => $tax,
            'net_amount' => round($gross - $tax, 2),
            'event_type' => $conversion->event_type,
            'status' => 'pending',
        ]);
    }

    /**
     * Setujui komisi -- baru di titik INI uangnya benar-benar masuk ke
     * wallet affiliate (lihat AffiliateWalletService::creditFromCommission).
     * Komisi yang masih pending TIDAK ada di saldo wallet sama sekali,
     * supaya admin bisa membatalkan transaksi yang di-refund sebelum
     * uangnya sempat cair.
     */
    /**
     * Baris komisi dikunci lalu status dicek ULANG memakai baris yang
     * sudah terkunci itu, dan ubah-status + kredit-wallet dijadikan SATU
     * transaksi. Tanpa ini, dua klik "Setujui" yang datang nyaris
     * bersamaan bisa sama-sama lolos cek status=pending sebelum salah
     * satunya sempat menulis, lalu wallet affiliate ke-KREDIT DUA KALI
     * untuk satu komisi yang sama -- dan kalau creditFromCommission()
     * gagal di tengah jalan, status tidak ikut ter-approve sendirian
     * tanpa saldo yang menyertainya.
     */
    public function approve(AffiliateCommission $commission, Admin $admin): AffiliateCommission
    {
        return DB::transaction(function () use ($commission, $admin) {
            $locked = AffiliateCommission::query()->lockForUpdate()->findOrFail($commission->id);

            if ($locked->status !== 'pending') {
                throw new AffiliateException('Komisi ini sudah diproses sebelumnya.');
            }

            if (class_exists(\App\Models\AffiliateFraudFlag::class)
                && \App\Models\AffiliateFraudFlag::where('affiliate_conversion_id', $locked->affiliate_conversion_id)
                    ->where('status', 'review')
                    ->exists()) {
                throw new AffiliateException('Komisi ditahan karena masih menunggu review fraud.');
            }

            $locked->update([
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            $this->wallet->creditFromCommission($locked);
            $this->audit->record('commission_approved', $locked->affiliate, $admin, ['status' => 'pending'], ['status' => 'approved']);

            return $locked;
        });
    }

    /**
     * Batalkan komisi. Kalau statusnya sudah "approved" (sudah kadung
     * masuk wallet), saldo dikoreksi balik lewat penyesuaian manual --
     * bukan menghapus baris ledger komisi sebelumnya, supaya riwayat
     * kenapa saldo naik lalu turun lagi tetap terbaca.
     */
    public function cancel(AffiliateCommission $commission, string $reason, Admin $admin): AffiliateCommission
    {
        return DB::transaction(function () use ($commission, $reason, $admin) {
            $locked = AffiliateCommission::query()->lockForUpdate()->findOrFail($commission->id);

            if ($locked->status === 'cancelled') {
                throw new AffiliateException('Komisi ini sudah dibatalkan.');
            }

            $wasApproved = $locked->status === 'approved';

            $locked->update([
                'status' => 'cancelled',
                'cancelled_reason' => $reason,
            ]);

            if ($wasApproved) {
                $this->wallet->adjust(
                    $locked->affiliate,
                    -1 * (float) ($locked->net_amount ?? $locked->amount),
                    "Pembatalan komisi #{$locked->id}: {$reason}",
                    $admin->id,
                );
            }

            $this->audit->record('commission_cancelled', $locked->affiliate, $admin, ['status' => $commission->status], ['status' => 'cancelled'], $reason);

            return $locked;
        });
    }

    public function reverse(AffiliateCommission $commission, string $reason, ?Admin $admin = null): AffiliateCommission
    {
        return DB::transaction(function () use ($commission, $reason, $admin) {
            $locked = AffiliateCommission::query()->lockForUpdate()->findOrFail($commission->id);

            if ($locked->status !== 'approved') {
                throw new AffiliateException('Hanya komisi approved yang dapat direversal.');
            }

            if ($locked->reversal()->exists()) {
                throw new AffiliateException('Komisi ini sudah direversal.');
            }

            $reversal = AffiliateCommissionReversal::create([
                'affiliate_commission_id' => $locked->id,
                'affiliate_id' => $locked->affiliate_id,
                'amount' => $locked->net_amount ?? $locked->amount,
                'reason' => $reason,
                'admin_id' => $admin?->id,
            ]);

            $this->wallet->reverseCommission($reversal, $admin?->id);
            $locked->update(['status' => 'cancelled', 'reversed_at' => now(), 'cancelled_reason' => $reason]);
            $this->audit->record('commission_reversed', $locked->affiliate, $admin, ['status' => 'approved'], ['status' => 'cancelled'], $reason);

            return $locked;
        });
    }

    public function reverseForInvoice(int $invoiceId, string $reason, ?Admin $admin = null): int
    {
        $count = 0;
        AffiliateCommission::whereHas('conversion', fn ($query) => $query->where('invoice_id', $invoiceId))
            ->where('status', 'approved')
            ->with('affiliate')
            ->get()
            ->each(function (AffiliateCommission $commission) use (&$count, $reason, $admin) {
                $this->reverse($commission, $reason, $admin);
                $count++;
            });

        return $count;
    }

    /**
     * Tarif komisi DASAR yang berlaku untuk affiliate ini (override
     * affiliate kalau ada, kalau tidak default global Settings) --
     * dipakai untuk MENAMPILKAN "program apa" ke affiliate/admin.
     * Belum memperhitungkan override per-campaign (itu baru diketahui
     * saat konversi sungguhan terjadi lewat calculate()), tapi tarif
     * dasar ini sudah cukup untuk menjawab pertanyaan "saya dapat
     * berapa persen".
     *
     * @return array{0: string, 1: float} [tipe, nilai]
     */
    public function baseRateFor(Affiliate $affiliate): array
    {
        return $this->resolveRate($affiliate, null);
    }

    /**
     * @return array{type: string, value: float, duration_days: ?int}
     */
    public function rateFor(AffiliateConversion $conversion): array
    {
        $affiliate = $conversion->affiliate;
        $campaign = $conversion->referral?->click?->campaign;

        if ($campaign && $campaign->commission_type && $campaign->commission_value !== null) {
            return [
                'type' => $campaign->commission_type,
                'value' => (float) $campaign->commission_value,
                'duration_days' => null,
            ];
        }

        $rule = AffiliateCommissionRule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($conversion) {
                $query->whereNull('product_type')->orWhere('product_type', $conversion->product_type);
            })
            ->where(function ($query) use ($conversion) {
                $query->where('event_type', $conversion->event_type)
                    ->orWhere('event_type', 'every_payment');
            })
            ->orderByRaw('CASE WHEN product_type IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN event_type = ? THEN 0 ELSE 1 END', [$conversion->event_type])
            ->orderBy('priority')
            ->first();

        if ($rule) {
            return [
                'type' => $rule->commission_type,
                'value' => (float) $rule->commission_value,
                'duration_days' => $rule->duration_days,
            ];
        }

        [$type, $value] = $this->resolveRate($affiliate, $campaign);

        return ['type' => $type, 'value' => $value, 'duration_days' => null];
    }

    /**
     * @return array{0: string, 1: float} [tipe, nilai]
     */
    private function resolveRate(Affiliate $affiliate, $campaign): array
    {
        if ($campaign && $campaign->commission_type && $campaign->commission_value !== null) {
            return [$campaign->commission_type, (float) $campaign->commission_value];
        }

        if ($affiliate->commission_type && $affiliate->commission_value !== null) {
            return [$affiliate->commission_type, (float) $affiliate->commission_value];
        }

        return [
            Setting::get('affiliate_commission_type', 'percentage'),
            (float) Setting::get('affiliate_commission_value', 10),
        ];
    }
}
