<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateConversion;
use App\Models\AffiliateReferral;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\HostingAccount;
use App\Models\Domain;

/**
 * Titik proses TUNGGAL saat invoice lunas untuk urusan affiliate --
 * dipanggil dari job billing setelah invoice benar-benar paid. Kalau client
 * pemilik invoice ini punya AffiliateReferral yang masih "pending",
 * konversi + komisi dibuat sekali untuk invoice ini.
 */
class AffiliateConversionService
{
    public function __construct(private readonly AffiliateCommissionService $commissions) {}

    public function processInvoicePaid(Invoice $invoice): void
    {
        // Invoice isi ulang saldo tidak menghasilkan komisi -- itu bukan
        // pembelian produk, cuma memindahkan uang klien ke saldo mereka
        // sendiri.
        if ($invoice->is_topup) {
            return;
        }

        // Sudah ada konversi untuk invoice ini (unique constraint di
        // migration juga menjaga ini, cek di sini supaya tidak perlu
        // menangkap exception constraint di jalur normal).
        if (AffiliateConversion::where('invoice_id', $invoice->id)->exists()) {
            return;
        }

        $eventType = $this->eventType($invoice);
        $previousConversions = AffiliateConversion::where('client_id', $invoice->client_id)->count();
        $mode = Setting::get('affiliate_commission_mode', 'every_payment');
        $repeat = filter_var(Setting::get('affiliate_commission_repeat', true), FILTER_VALIDATE_BOOLEAN);

        if (($mode === 'first_order' && $previousConversions > 0)
            || ($mode === 'first_payment' && $previousConversions > 0)
            || (! $repeat && $previousConversions > 0)) {
            return;
        }

        $statuses = ['pending', 'converted'];

        $referral = AffiliateReferral::where('client_id', $invoice->client_id)
            ->whereIn('status', $statuses)
            ->with('affiliate')
            ->first();

        if (! $referral || ! $referral->affiliate?->isApproved()) {
            return;
        }

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $referral->affiliate_id,
            'affiliate_referral_id' => $referral->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'product_type' => $this->productType($invoice),
            'event_type' => $eventType,
            'amount' => $invoice->total,
            'net_paid_amount' => $invoice->total,
        ]);

        if ($referral->status === 'pending') {
            $referral->update(['status' => 'converted', 'converted_at' => now()]);
        }

        $this->commissions->createForConversion($conversion);
    }

    /**
     * "mixed" kalau invoice ini berisi lebih dari satu jenis order
     * (mis. domain + hosting dibeli bersamaan lewat satu invoice
     * checkout) -- lihat kolom order_type di tabel orders.
     */
    private function productType(Invoice $invoice): ?string
    {
        $types = $invoice->items()
            ->with('order:id,order_type,product_name')
            ->get()
            ->map(function ($item) {
                $type = $item->order?->order_type;
                $name = mb_strtolower((string) ($item->order?->product_name ?: $item->description));

                if (str_contains($name, 'reseller') && str_contains($name, 'domain')) {
                    return 'reseller_domain';
                }
                if (str_contains($name, 'reseller')) {
                    return 'reseller_hosting';
                }
                if (str_contains($name, 'ssl')) {
                    return 'ssl';
                }
                if (str_contains($name, 'addon') || str_contains($name, 'add-on')) {
                    return 'addon';
                }

                return $type;
            })
            ->filter()
            ->unique();

        if ($types->isEmpty()) {
            return null;
        }

        return $types->count() === 1 ? $types->first() : 'mixed';
    }

    private function eventType(Invoice $invoice): string
    {
        if (HostingAccount::where('renewal_invoice_id', $invoice->id)->exists()
            || Domain::where('renewal_invoice_id', $invoice->id)->exists()) {
            return 'renewal';
        }

        if (HostingAccount::where('pending_upgrade_invoice_id', $invoice->id)->exists()) {
            return 'upgrade';
        }

        return AffiliateConversion::where('client_id', $invoice->client_id)->exists()
            ? 'every_payment'
            : 'first_order';
    }
}
