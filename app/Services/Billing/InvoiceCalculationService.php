<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\InvoiceCalculationException;
use App\Models\Invoice;
use App\Models\Tax;

/**
 * Sumber tunggal perhitungan invoice.
 *
 * Nilai uang diproses dalam integer cent untuk menghindari keputusan bisnis
 * berbasis floating point. Database tetap menyimpan decimal(12,2).
 */
class InvoiceCalculationService
{
    /**
     * @return array{amount:string,tax:string,discount:string,total:string,tax_rate:?string}
     */
    public function calculate(string|float|int $amount, string|float|int $tax = 0, string|float|int $discount = 0, ?string $taxRate = null): array
    {
        $amountCents = $this->toCents($amount);
        $taxCents = $this->toCents($tax);
        $discountCents = $this->toCents($discount);

        if ($amountCents < 0) {
            throw InvoiceCalculationException::negativeAmount((float) $amount);
        }

        if ($taxCents < 0 || $discountCents < 0) {
            throw new InvoiceCalculationException('Tax dan discount tidak boleh negatif.');
        }

        if ($discountCents > $amountCents + $taxCents) {
            throw InvoiceCalculationException::discountExceedsTotal((float) $discount, (float) $amount, (float) $tax);
        }

        return [
            'amount' => $this->money($amountCents),
            'tax' => $this->money($taxCents),
            'discount' => $this->money($discountCents),
            'total' => $this->money(max(0, $amountCents + $taxCents - $discountCents)),
            'tax_rate' => $taxRate,
        ];
    }

    public function calculateTax(string|float|int $taxableAmount, ?Tax $tax): string
    {
        if (! $tax || ! $tax->is_active) {
            return '0.00';
        }

        $base = $this->toCents($taxableAmount);
        $rate = (float) $tax->rate_percentage;
        $taxCents = (int) round($base * $rate / 100, 0, PHP_ROUND_HALF_UP);

        return $this->money($taxCents);
    }

    public function total(string|float|int $amount, string|float|int $tax = 0, string|float|int $discount = 0): string
    {
        return $this->calculate($amount, $tax, $discount)['total'];
    }

    private function toCents(string|float|int $value): int
    {
        $normalized = number_format((float) $value, 2, '.', '');
        return (int) round(((float) $normalized) * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
