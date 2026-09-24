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

        // Overdue is deliberately not payable. Reopening an overdue invoice
        // must be an explicit business action that creates a new invoice or
        // reactivation flow, not an accidental gateway callback.
        if (in_array($invoice->status, ['cancelled', 'overdue', 'refunded'], true)
            || ($invoice->status === 'unpaid' && $invoice->due_date?->isPast())) {
            return ['allowed' => false, 'message' => $invoice->status === 'refunded'
                ? 'Invoice ini sudah direfund dan tidak bisa dibayar ulang.'
                : 'Invoice ini sudah melewati batas pembayaran atau tidak lagi aktif.', 'domain' => null];
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