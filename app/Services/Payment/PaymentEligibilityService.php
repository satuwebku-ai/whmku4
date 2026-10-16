<?php

namespace App\Services\Payment;

use App\Exceptions\Payment\PaymentNotAllowedException;
use App\Models\Domain;
use App\Models\DomainDocument;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tld;

/**
 * Satu gerbang server-side untuk seluruh jalur pembayaran.
 *
 * Controller boleh menonaktifkan tombol di browser, tetapi keputusan final
 * selalu dibuat di sini sebelum payment dibuat maupun saat callback gateway
 * mencoba menandai payment sebagai paid.
 */
class PaymentEligibilityService
{
    /**
     * @return array{allowed: bool, message: ?string, domain: ?Domain}
     */
    public function check(Invoice $invoice): array
    {
        if ($invoice->is_topup) {
            return ['allowed' => true, 'message' => null, 'domain' => null];
        }

        if ($invoice->status === 'paid') {
            return ['allowed' => false, 'message' => 'Invoice ini sudah lunas.', 'domain' => null];
        }

        // Invoice unpaid/overdue TETAP bisa dibayar, termasuk yang jatuh
        // temponya hari ini atau sudah lewat. Alur suspend memberi masa
        // toleransi lalu "bayar -> reaktivasi"; kalau pembayaran ditolak di
        // sini, klien yang menunggak tidak punya jalan untuk mengaktifkan
        // layanannya lagi, dan uang dari gateway yang masuk setelah jam
        // 00:00 hari jatuh tempo malah ditolak.
        //
        // Batas akhirnya bukan tanggal, melainkan pembatalan invoice:
        // checkout baru yang overdue dibatalkan oleh
        // lumora:cancel-overdue-checkouts sesudah masa toleransi, dan
        // invoice yang sudah dibatalkan/refund memang tidak bisa dibayar.
        if ($invoice->status === 'cancelled') {
            return ['allowed' => false, 'message' => 'Invoice ini sudah dibatalkan dan tidak bisa dibayar.', 'domain' => null];
        }

        if ($invoice->status === 'refunded') {
            return ['allowed' => false, 'message' => 'Invoice ini sudah direfund dan tidak bisa dibayar ulang.', 'domain' => null];
        }

        // Aturan bisnis yang TIDAK boleh bisa dilewati lewat invoice lama
        // (data pemulihan/seeder, invoice yang dibuat sebelum aturan ada):
        // ID Protection untuk domain .id (PANDI) dan perpanjangan domain
        // di luar jendela H-30.
        if ($violation = $this->ruleViolation($invoice)) {
            return [
                'allowed' => false,
                'message' => $violation['message'],
                'domain' => $violation['domain'],
            ];
        }

        if ($domain = $this->documentBlocker($invoice)) {
            return [
                'allowed' => false,
                'message' => "Berkas persyaratan untuk {$domain->domain_name} belum lengkap atau belum disetujui. Lengkapi dulu sebelum melanjutkan pembayaran.",
                'domain' => $domain,
            ];
        }

        return ['allowed' => true, 'message' => null, 'domain' => null];
    }

    public function assertPayable(Invoice $invoice): void
    {
        $result = $this->check($invoice);

        if (! $result['allowed']) {
            throw new PaymentNotAllowedException(
                $result['message'] ?? 'Pembayaran invoice tidak diizinkan.',
                $invoice->id,
                $result['domain']?->id,
            );
        }
    }

    /**
     * Invoice yang isinya melanggar aturan produk. Dipakai oleh check()
     * (jadi otomatis berlaku untuk bayar klien, saldo, webhook gateway,
     * dan "tandai lunas" admin) dan oleh perintah
     * lumora:cancel-invalid-invoices untuk membersihkan data lama.
     *
     * Urutan sumber data: (0) rujukan layanan di baris invoice, (1) tautan
     * sementara di domain, (2) teks deskripsi — dua yang terakhir hanya
     * jaring pengaman untuk invoice lama yang belum di-backfill.
     *
     * @return array{code: string, message: string, domain: ?Domain}|null
     */
    public function ruleViolation(Invoice $invoice): ?array
    {
        // 0) Rujukan layanan di baris invoice — tanpa menebak teks.
        if (InvoiceItem::supportsServiceRef()) {
            foreach ($invoice->items()->where('service_type', InvoiceItem::SERVICE_DOMAIN)->get() as $item) {
                $domain = Domain::with('tld')->find($item->service_id);
                if (! $domain) {
                    continue;
                }

                if ($item->item_type === InvoiceItem::TYPE_PRIVACY && ! $domain->supportsPrivacy()) {
                    return $this->privacyViolation($domain);
                }

                if ($item->item_type === InvoiceItem::TYPE_RENEWAL && ! $domain->isWithinRenewalWindow()) {
                    return $this->renewalViolation($domain);
                }
            }
        }

        // 1) ID Protection / WHOIS Privacy dilarang PANDI untuk keluarga .id.
        $privacyDomain = Domain::with('tld')->where('privacy_invoice_id', $invoice->id)->first();
        if ($privacyDomain && ! $privacyDomain->supportsPrivacy()) {
            return $this->privacyViolation($privacyDomain);
        }

        foreach ($invoice->items()->pluck('description') as $description) {
            if (preg_match('/ID Protection\s+[\x{2014}-]\s+([a-z0-9.-]+)/iu', (string) $description, $m)
                && Tld::isIdFamily($m[1])) {
                return $this->privacyViolation(Domain::where('domain_name', $m[1])->first(), $m[1]);
            }
        }

        // 2) Perpanjangan domain hanya dibuka maksimal H-30 sebelum expiry.
        $renewalDomain = Domain::where('renewal_invoice_id', $invoice->id)->first();
        if ($renewalDomain && ! $renewalDomain->isWithinRenewalWindow()) {
            return $this->renewalViolation($renewalDomain);
        }

        return null;
    }

    private function privacyViolation(?Domain $domain, ?string $name = null): array
    {
        $name ??= $domain?->domain_name ?? 'domain ini';

        return [
            'code' => 'privacy_not_allowed',
            'message' => "ID Protection tidak tersedia untuk {$name} (domain .id dilarang PANDI). Invoice ini tidak dapat dibayar.",
            'domain' => $domain,
        ];
    }

    private function renewalViolation(Domain $domain): array
    {
        $opens = $domain->expiry_date?->copy()->subDays(30)->format('d M Y');

        return [
            'code' => 'renewal_too_early',
            'message' => "Perpanjangan {$domain->domain_name} baru dapat dibayar mulai {$opens} (H-30 sebelum kedaluwarsa).",
            'domain' => $domain,
        ];
    }

    public function documentBlocker(Invoice $invoice): ?Domain
    {
        $orderIds = $invoice->items()->pluck('order_id')->filter()->unique();

        $domains = Domain::with(['tld', 'documents'])
            ->where(function ($query) use ($orderIds, $invoice) {
                if ($orderIds->isNotEmpty()) {
                    $query->whereIn('order_id', $orderIds);
                }

                $query->orWhere('renewal_invoice_id', $invoice->id);
            })
            ->get();

        foreach ($domains as $domain) {
            // progressFor() selalu dihitung ulang. Kolom timestamp hanya
            // audit trail, bukan bypass pembayaran yang bisa menjadi basi
            // setelah klien mengganti atau admin menolak dokumen.
            if ($domain->documents_verified_at) {
                continue;
            }

            if (! DomainDocument::progressFor($domain)['complete']) {
                return $domain;
            }
        }

        return null;
    }
}