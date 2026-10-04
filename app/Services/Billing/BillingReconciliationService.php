<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recovery guard for financial records that became inconsistent because a
 * worker/webhook stopped after one database write but before the next one.
 *
 * This service deliberately does not guess that an unpaid invoice was paid.
 * It only repairs records where the source-of-truth payment is already
 * marked paid, or where a paid top-up is missing its idempotent credit.
 */
class BillingReconciliationService
{
    public function scan(): array
    {
        $paidPaymentsWithUnpaidInvoice = Payment::query()
            ->where('status', 'paid')
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'paid'))
            ->count();

        $paidInvoicesMissingCharge = Invoice::query()
            ->where('status', 'paid')
            ->whereHas('payments', fn ($q) => $q->where('status', 'paid'))
            ->whereDoesntHave('transactions', fn ($q) => $q->where('type', 'charge'))
            ->count();

        $paidTopupsMissingCredit = Invoice::query()
            ->where('status', 'paid')
            ->where('is_topup', true)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('credits')
                    ->whereColumn('credits.invoice_id', 'invoices.id')
                    ->where('credits.type', 'topup');
            })
            ->count();

        $stuckOrders = Invoice::query()
            ->where('status', 'paid')
            ->whereHas('items.order', fn ($q) => $q->whereIn('status', ['paid', 'provisioning', 'failed']))
            ->count();

        return [
            'balance_ledger_mismatch' => count($this->balanceMismatches()),
            'paid_payment_invoice_mismatch' => $paidPaymentsWithUnpaidInvoice,
            'paid_invoice_missing_charge' => $paidInvoicesMissingCharge,
            'paid_topup_missing_credit' => $paidTopupsMissingCredit,
            'paid_invoice_with_unfinished_order' => $stuckOrders,
        ];
    }

    /**
     * Klien yang saldonya (clients.balance) tidak sama dengan jumlah semua
     * mutasi buku besar (credits). Hanya dilaporkan, TIDAK diperbaiki
     * otomatis: tidak ada cara menebak mana yang benar, dan akun lama
     * bisa punya saldo awal dari sebelum buku besar ada.
     *
     * @return array<int, array{client_id:int,name:string,balance:string,ledger:string}>
     */
    public function balanceMismatches(): array
    {
        $ledger = DB::table('credits')
            ->selectRaw('client_id, SUM(amount) AS total')
            ->groupBy('client_id');

        return DB::table('clients')
            ->leftJoinSub($ledger, 'l', 'l.client_id', '=', 'clients.id')
            ->selectRaw('clients.id AS client_id, clients.name AS name, clients.balance AS balance, COALESCE(l.total, 0) AS ledger')
            ->get()
            ->filter(fn ($r) => \App\Support\Money::cents($r->balance) !== \App\Support\Money::cents($r->ledger))
            ->map(fn ($r) => [
                'client_id' => (int) $r->client_id,
                'name' => (string) $r->name,
                'balance' => \App\Support\Money::fromCents(\App\Support\Money::cents($r->balance)),
                'ledger' => \App\Support\Money::fromCents(\App\Support\Money::cents($r->ledger)),
            ])
            ->values()
            ->all();
    }

    /**
     * Repair only deterministic ledger gaps. Returns a count per repair type.
     */
    public function repair(): array
    {
        $result = [
            'invoice_status_repaired' => 0,
            'charge_repaired' => 0,
            'topup_repaired' => 0,
        ];

        Payment::query()
            ->where('status', 'paid')
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'paid'))
            ->with('invoice')
            ->chunkById(100, function (Collection $payments) use (&$result) {
                foreach ($payments as $payment) {
                    DB::transaction(function () use ($payment, &$result) {
                        $locked = Payment::query()->lockForUpdate()->with('invoice')->find($payment->id);
                        $invoice = $locked?->invoice ? Invoice::query()->lockForUpdate()->find($locked->invoice_id) : null;

                        if (! $locked || ! $invoice || $locked->status !== 'paid' || $invoice->status === 'paid') {
                            return;
                        }

                        $invoice->update([
                            'status' => 'paid',
                            'paid_at' => $locked->paid_at ?? now(),
                            'payment_method' => $locked->payment_method ?? $locked->gateway?->name,
                        ]);
                        $result['invoice_status_repaired']++;
                    });
                }
            });

        Invoice::query()
            ->where('status', 'paid')
            ->whereHas('payments', fn ($q) => $q->where('status', 'paid'))
            ->whereDoesntHave('transactions', fn ($q) => $q->where('type', 'charge'))
            ->with(['payments' => fn ($q) => $q->where('status', 'paid')->oldest('id')])
            ->chunkById(100, function (Collection $invoices) use (&$result) {
                foreach ($invoices as $invoice) {
                    $payment = $invoice->payments->first();
                    if (! $payment) {
                        continue;
                    }

                    Transaction::firstOrCreate(
                        ['idempotency_key' => 'payment:' . $payment->id . ':paid'],
                        [
                            'client_id' => $payment->client_id,
                            'invoice_id' => $invoice->id,
                            'payment_id' => $payment->id,
                            'type' => 'charge',
                            'amount' => $payment->total,
                            'description' => 'Recovery pembayaran invoice ' . ($invoice->invoice_number ?? $invoice->id),
                        ]
                    );
                    $result['charge_repaired']++;
                }
            });

        Invoice::query()
            ->where('status', 'paid')
            ->where('is_topup', true)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('credits')
                    ->whereColumn('credits.invoice_id', 'invoices.id')
                    ->where('credits.type', 'topup');
            })
            ->chunkById(100, function (Collection $invoices) use (&$result) {
                foreach ($invoices as $invoice) {
                    app(TopupService::class)->applyPaidInvoice($invoice);
                    $result['topup_repaired']++;
                }
            });

        return $result;
    }
}