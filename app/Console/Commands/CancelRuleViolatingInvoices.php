<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Billing\InvoiceService;
use App\Services\Payment\PaymentEligibilityService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Batalkan invoice BELUM LUNAS yang isinya melanggar aturan produk, mis.
 * ID Protection untuk domain .id (PANDI) atau perpanjangan domain yang
 * terbit di luar jendela H-30. Pembatalan lewat InvoiceService::cancel()
 * sehingga renewal_invoice_id / privacy_invoice_id domain ikut dilepas dan
 * payment yang masih menunggu ditutup.
 *
 * Invoice yang SUDAH LUNAS tidak disentuh — hanya dilaporkan, karena perlu
 * refund manual.
 */
class CancelRuleViolatingInvoices extends Command
{
    protected $signature = 'lumora:cancel-invalid-invoices
                            {--dry : Hanya tampilkan, tanpa membatalkan}';

    protected $description = 'Batalkan invoice unpaid yang melanggar aturan (ID Protection .id, renewal di luar H-30)';

    public function handle(PaymentEligibilityService $rules, InvoiceService $invoices): int
    {
        $dry = (bool) $this->option('dry');
        $cancelled = 0;
        $needsRefund = 0;
        $failed = 0;

        Invoice::query()
            ->where('is_topup', false)
            ->whereIn('status', ['unpaid', 'overdue', 'paid'])
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($rules, $invoices, $dry, &$cancelled, &$needsRefund, &$failed) {
                foreach ($chunk as $invoice) {
                    $violation = $rules->ruleViolation($invoice);

                    if (! $violation) {
                        continue;
                    }

                    if ($invoice->status === 'paid') {
                        $needsRefund++;
                        $this->warn("  {$invoice->invoice_number} SUDAH LUNAS tetapi melanggar aturan — perlu refund manual: {$violation['message']}");
                        continue;
                    }

                    $this->line("  {$invoice->invoice_number} [{$violation['code']}] {$violation['message']}");

                    if ($dry) {
                        $cancelled++;
                        continue;
                    }

                    try {
                        $invoices->cancel($invoice);
                        $invoice->refresh()->update([
                            'notes' => trim(($invoice->notes ? $invoice->notes . "\n" : '') . 'Dibatalkan otomatis: ' . $violation['message']),
                        ]);
                        $cancelled++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->error("  gagal membatalkan {$invoice->invoice_number}: " . $e->getMessage());
                    }
                }
            });

        $this->newLine();
        $this->info(($dry ? 'Simulasi: ' : '') . "{$cancelled} invoice " . ($dry ? 'AKAN dibatalkan' : 'dibatalkan') . ", {$needsRefund} lunas perlu refund manual, {$failed} gagal.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
