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
                ['Saldo klien ≠ jumlah buku besar', $scan['balance_ledger_mismatch'] ?? 0],
                ['Paid payment → invoice belum paid', $scan['paid_payment_invoice_mismatch']],
                ['Paid invoice → charge transaction hilang', $scan['paid_invoice_missing_charge']],
                ['Paid top-up → credit hilang', $scan['paid_topup_missing_credit']],
                ['Paid invoice → order belum selesai', $scan['paid_invoice_with_unfinished_order']],
            ]);

            if (($scan['balance_ledger_mismatch'] ?? 0) > 0) {
                $rows = $service->balanceMismatches();

                $this->warn('Saldo tidak cocok dengan buku besar (tidak diperbaiki otomatis):');
                $this->table(
                    ['Klien', 'Nama', 'Saldo', 'Buku besar'],
                    array_map(fn ($r) => [$r['client_id'], $r['name'], $r['balance'], $r['ledger']], array_slice($rows, 0, 50)),
                );

                try {
                    app(\App\Services\Notification\NotificationService::class)->balanceMismatch($rows);
                } catch (Throwable $e) {
                    report($e);
                }
            }

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
        }

        return self::FAILURE;
    }
}
