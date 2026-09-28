<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\CronJob;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Services\Billing\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use Throwable;

/**
 * Rapikan checkout baru yang sudah overdue terlalu lama.
 *
 * Renewal, upgrade, addon, dan top-up tidak ikut disentuh: hanya invoice
 * yang punya order_id langsung (checkout legacy) atau invoice_item dengan
 * order_id (checkout multi-item).
 */
class CancelOverdueCheckouts extends Command
{
    protected $signature = 'lumora:cancel-overdue-checkouts
                            {--days= : Jumlah hari setelah jatuh tempo sebelum checkout dibatalkan}';

    protected $description = 'Batalkan checkout baru yang overdue terlalu lama dan lepaskan order/domain terkait';

    public function handle(InvoiceService $invoices): int
    {
        ob_start();
        $result = $this->handleJob($invoices);
        $output = ob_get_clean();
        echo $output;

        CronJob::recordExecution('lumora:cancel-overdue-checkouts', $result === self::SUCCESS, $output);

        return $result;
    }

    private function handleJob(InvoiceService $invoices): int
    {
        $days = max(0, min((int) ($this->option('days') ?? Setting::get('checkout_cancel_grace_days', 3)), 90));
        $cutoff = now()->subDays($days)->toDateString();
        $cancelled = 0;
        $failed = 0;

        Invoice::query()
            ->where('status', 'overdue')
            ->where('is_topup', false)
            ->whereDate('due_date', '<=', $cutoff)
            ->where(function ($query) {
                $query->whereNotNull('order_id')
                    ->orWhereHas('items', fn ($items) => $items->whereNotNull('order_id'));
            })
            ->select('id')
            ->chunkById(100, function ($invoiceRows) use ($invoices, $days, &$cancelled, &$failed) {
                foreach ($invoiceRows as $invoiceRow) {
                    try {
                        if ($this->cancelOne($invoiceRow->id, $invoices, $days)) {
                            $cancelled++;
                        }
                    } catch (Throwable $e) {
                        $failed++;
                        $this->error("Invoice #{$invoiceRow->id} gagal dibatalkan: {$e->getMessage()}");
                    }
                }
            });

        $this->info($cancelled > 0
            ? "{$cancelled} checkout overdue dibatalkan dan order/domain terkait ditutup."
            : 'Tidak ada checkout overdue yang perlu dibatalkan.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function cancelOne(int $invoiceId, InvoiceService $invoices, int $days): bool
    {
        return (bool) DB::transaction(function () use ($invoiceId, $invoices, $days) {
            $invoice = Invoice::query()->lockForUpdate()->find($invoiceId);

            if (! $invoice || $invoice->status !== 'overdue' || $invoice->is_topup
                || ! $invoice->due_date?->lessThanOrEqualTo(now()->subDays($days))) {
                return false;
            }

            $orderIds = InvoiceItem::query()
                ->where('invoice_id', $invoice->id)
                ->whereNotNull('order_id')
                ->pluck('order_id')
                ->merge([$invoice->order_id])
                ->filter()
                ->unique();

            if ($orderIds->isEmpty()) {
                return false;
            }

            $invoices->cancel($invoice);

            $orders = Order::query()
                ->whereIn('id', $orderIds)
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                $status = $order->status instanceof OrderStatus
                    ? $order->status
                    : OrderStatus::tryFrom((string) $order->status);

                if ($status && in_array($status, [
                    OrderStatus::Draft,
                    OrderStatus::RequirementsPending,
                    OrderStatus::RequirementsReview,
                    OrderStatus::RequirementsRejected,
                    OrderStatus::RequirementsApproved,
                    OrderStatus::LegacyPending,
                    OrderStatus::PendingPayment,
                ], true)) {
                    $order->transitionTo(OrderStatus::Expired, 'Checkout dibatalkan otomatis karena invoice overdue terlalu lama.');
                }

                Domain::query()
                    ->where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->whereNotIn('provision_status', ['registered', 'provisioning', 'transfer_pending'])
                    ->update([
                        'status' => 'cancelled',
                        'provision_message' => 'Dibatalkan otomatis karena checkout tidak dibayar.',
                    ]);
            }

            return true;
        });
    }
}