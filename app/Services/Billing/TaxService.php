<?php

namespace App\Services\Billing;

use App\Models\Tax;

/**
 * Pintu tunggal untuk pemilihan dan perhitungan pajak invoice.
 *
 * Tax tidak diterapkan diam-diam: invoice hanya dikenai pajak bila tax_id
 * dipilih/dikonfigurasi. Ini menjaga harga lama tetap kompatibel sekaligus
 * memberi satu aturan canonical untuk invoice baru.
 */
class TaxService
{
    public function findActive(?int $taxId): ?Tax
    {
        if (! $taxId) {
            return null;
        }

        return Tax::query()->whereKey($taxId)->where('is_active', true)->first();
    }

    public function assertUsable(?int $taxId): ?Tax
    {
        if (! $taxId) {
            return null;
        }

        $tax = $this->findActive($taxId);
        if (! $tax) {
            throw new \InvalidArgumentException('Tarif pajak tidak aktif atau tidak ditemukan.');
        }

        if ((float) $tax->rate_percentage < 0 || (float) $tax->rate_percentage > 100) {
            throw new \InvalidArgumentException('Tarif pajak harus berada di antara 0% dan 100%.');
        }

        return $tax;
    }

    /**
     * Snapshot tax ke invoice: simpan tax_id + rate + nominal agar histori
     * invoice tidak berubah ketika admin mengubah tarif pajak di kemudian hari.
     */
    public function applyToInvoice(\App\Models\Invoice $invoice, ?Tax $tax): void
    {
        if (! $tax) {
            $invoice->forceFill([
                'tax_id' => null,
                'tax_rate' => null,
                'tax' => '0.00',
            ]);
            $invoice->recalculateTotal();
            return;
        }

        $invoice->forceFill([
            'tax_id' => $tax->id,
            'tax_rate' => $tax->rate_percentage,
            'tax' => app(InvoiceCalculationService::class)->calculateTax(
                $this->taxableBase($invoice),
                $tax,
            ),
        ]);
        $invoice->recalculateTotal();
    }

    public function taxableBase(\App\Models\Invoice $invoice): string
    {
        // Pajak dihitung setelah discount dan tidak pernah negatif.
        $amount = (float) ($invoice->amount ?? 0);
        $discount = (float) ($invoice->discount ?? 0);

        return number_format(max(0, $amount - $discount), 2, '.', '');
    }
}
