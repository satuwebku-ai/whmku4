<?php

namespace App\Services\Payment;

use App\Exceptions\Payment\PaymentNotAllowedException;
use App\Models\Domain;
use App\Models\DomainDocument;
use App\Models\Invoice;

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