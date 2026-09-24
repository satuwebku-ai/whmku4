<?php

namespace App\Exceptions\Billing;

use App\Models\Invoice;

class InvoiceAlreadyPaidException extends InvoiceException
{
    public static function forInvoice(Invoice $invoice): self
    {
        return new self("Invoice {$invoice->invoice_number} sudah lunas.");
    }
}
