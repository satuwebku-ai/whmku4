<?php

namespace App\Exceptions\Billing;

class InvoiceNotFoundException extends InvoiceException
{
    public static function forId(int $id): self
    {
        return new self("Invoice #{$id} tidak ditemukan.");
    }

    public static function forNumber(string $invoiceNumber): self
    {
        return new self("Invoice {$invoiceNumber} tidak ditemukan.");
    }
}
