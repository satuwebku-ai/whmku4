<?php

namespace Tests\Feature\Billing;

use App\Jobs\Billing\ProcessPaidInvoice;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class ProcessPaidInvoiceQueueTest extends TestCase
{
    public function test_paid_invoice_job_uses_billing_queue_and_overlap_guard(): void
    {
        $job = new ProcessPaidInvoice(123);

        $this->assertSame('billing', $job->queue);
        $this->assertSame(900, $job->timeout);
        $this->assertCount(1, $job->middleware());
        $this->assertInstanceOf(WithoutOverlapping::class, $job->middleware()[0]);
        $this->assertSame(['invoice:123', 'billing', 'fulfillment'], $job->tags());
    }
}
