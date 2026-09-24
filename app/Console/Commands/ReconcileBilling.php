<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingReconciliationService;
use Illuminate\Console\Command;
use Throwable;

class ReconcileBilling extends Command
{
    protected $signature = 'lumora:reconcile-billing {--repair : Perbaiki gap finansial yang deterministik}';

    protected $description = 'Audit dan recovery ringan untuk payment, invoice, transaction ledger, dan top-up.';

    public function handle(BillingReconciliationService $service): int
    {
        try {
            $scan = $service->scan();

            $this->table(['Check', 'Count'], [
                ['Paid payment → invoice belum paid', $scan['paid_payment_invoice_mismatch']],
                ['Paid invoice → charge transaction hilang', $scan['paid_invoice_missing_charge']],
                ['Paid top-up → credit hilang', $scan['paid_topup_missing_credit']],
                ['Paid invoice → order belum selesai', $scan['paid_invoice_with_unfinished_order']],
            ]);

            if (! $this->option('repair')) {
                $this->comment('Audit saja. Gunakan --repair untuk recovery gap yang deterministik.');
                return self::SUCCESS;
            }

            $repaired = $service->repair();
            $this->table(['Repair', 'Count'], [
                ['Invoice status', $repaired['invoice_status_repaired']],
                ['Charge transaction', $repaired['charge_repaired']],
                ['Top-up credit', $repaired['topup_repaired']],
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Reconcile gagal: ' . $e->getMessage());
            report($e);
            return self::FAILURE;
        }
    }
}
