<?php

namespace App\Exceptions\Billing;

class InvoiceCalculationException extends InvoiceException
{
    public static function negativeAmount(float $amount): self
    {
        return new self("Nominal invoice tidak boleh negatif ({$amount}).");
    }

    public static function discountExceedsTotal(float $discount, float $amount, float $tax): self
    {
        return new self(
            "Diskon (Rp {$discount}) tidak boleh melebihi amount + tax (Rp " . ($amount + $tax) . ')'
        );
    }
}
