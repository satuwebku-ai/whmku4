<?php

namespace App\Listeners\Invoice;

use App\Events\Invoice\InvoicePaid;
use App\Jobs\Billing\ProcessPaidInvoice;

/**
 * InvoicePaid sudah menggunakan ShouldDispatchAfterCommit, jadi listener ini
 * boleh tipis dan synchronous: pekerjaan berat selalu masuk queue billing.
 */
class QueuePaidInvoiceProcessing
{
    public function handle(InvoicePaid $event): void
    {
        ProcessPaidInvoice::dispatch($event->invoice->id);
    }
}
