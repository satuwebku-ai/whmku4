<?php

namespace App\Jobs\Billing;

use App\Models\Invoice;
use App\Services\Affiliate\AffiliateConversionService;
use App\Services\Billing\CouponService;
use App\Services\Notification\NotificationService;
use App\Services\Provisioning\ProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPaidInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    /**
     * Retry invoice yang sama tidak boleh berjalan paralel, karena fulfillment
     * menyentuh stock reservation, provider API, credit, renewal, dan add-on.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('paid-invoice:' . $this->invoiceId))
                ->releaseAfter(60)
                ->expireAfter(1800),
        ];
    }

    public function tags(): array
    {
        return ['invoice:' . $this->invoiceId, 'billing', 'fulfillment'];
    }

    public function __construct(public readonly int $invoiceId)
    {
        $this->onQueue('billing');
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(
        ProvisioningService $provisioning,
        CouponService $coupons,
        NotificationService $notifications,
        AffiliateConversionService $affiliate,
    ): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice || $invoice->status !== 'paid') {
            return;
        }

        // Semua efek setelah pembayaran berada di satu job. Masing-masing
        // service punya guard idempotency sendiri karena job dapat diulang.
        $coupons->consumeForInvoice($invoice);

        if (! $invoice->is_topup) {
            $provisioning->consumeCheckoutReservations($invoice);
        }

        try {
            $notifications->invoicePaid($invoice);
        } catch (Throwable $e) {
            Log::warning('Notifikasi pembayaran gagal: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
        }

        if ($invoice->is_topup) {
            $provisioning->processTopupPayment($invoice);

            return;
        }

        $provisioning->provisionInvoice($invoice);
        $provisioning->processRenewalPayment($invoice);
        $provisioning->processUpgradePayment($invoice);
        $provisioning->processAddonPayment($invoice);
        $provisioning->processPrivacyPayment($invoice);
        $affiliate->processInvoicePaid($invoice);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Pemrosesan invoice paid gagal setelah retry.', [
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
        ]);
    }
}