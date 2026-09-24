<?php

namespace App\Exceptions\Billing;

use App\Models\Invoice;

class InvoiceOverdueException extends InvoiceException
{
    public static function forInvoice(Invoice $invoice): self
    {
        return new self("Invoice {$invoice->invoice_number} sudah melewati jatuh tempo ({$invoice->due_date?->format('d-m-Y')}).");
    }
}
