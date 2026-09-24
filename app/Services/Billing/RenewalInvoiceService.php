<?php

namespace App\Services\Billing;

use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu pintu pembuatan invoice renewal.
 *
 * Semua jalur (scheduler maupun tombol "Perpanjang Sekarang") memakai
 * service ini agar dua request yang datang bersamaan tidak membuat dua
 * invoice untuk siklus yang sama.
 */
class RenewalInvoiceService
{
    public function createHostingInvoice(HostingAccount $hosting): Invoice
    {
        return DB::transaction(function () use ($hosting) {
            $hosting = HostingAccount::query()->lockForUpdate()->findOrFail($hosting->id);

            if ($hosting->status !== 'active') {
                throw new RuntimeException('Hanya layanan hosting aktif yang dapat dibuatkan invoice renewal.');
            }

            if ($hosting->renewal_invoice_id) {
                return Invoice::findOrFail($hosting->renewal_invoice_id);
            }

            $amount = $hosting->renewalAmount();
            $invoice = $this->createInvoice(
                $hosting->client_id,
                $amount,
                $hosting->next_due_date,
            );

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => "Perpanjangan Hosting — {$hosting->domain} ({$hosting->package}, {$hosting->cycleLabel()})",
                'amount' => (float) $hosting->price,
            ]);

            foreach ($hosting->activeAddons()->get() as $addon) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => "Addon — {$addon->name} ({$hosting->domain}, {$hosting->cycleLabel()})",
                    'amount' => (float) $addon->price,
                ]);
            }

            foreach ($hosting->options()->get() as $option) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => "{$option->name} ({$hosting->domain}, {$hosting->cycleLabel()})",
                    'amount' => (float) $option->price,
                ]);
            }

            $invoice->recalculateFromItems();
            $hosting->update(['renewal_invoice_id' => $invoice->id]);

            return $invoice->fresh();
        });
    }

    public function createDomainInvoice(Domain $domain): Invoice
    {
        return DB::transaction(function () use ($domain) {
            $domain = Domain::query()->lockForUpdate()->findOrFail($domain->id);

            if ($domain->status !== 'active') {
                throw new RuntimeException('Hanya domain aktif yang dapat dibuatkan invoice renewal.');
            }

            if (! $domain->isWithinRenewalWindow()) {
                throw new RuntimeException('Domain belum masuk jendela renewal H-30.');
            }

            if ($domain->renewal_invoice_id) {
                return Invoice::findOrFail($domain->renewal_invoice_id);
            }

            $amount = $domain->renewalAmount();
            $invoice = $this->createInvoice(
                $domain->client_id,
                $amount,
                $domain->expiry_date,
            );

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => "Perpanjangan Domain — {$domain->domain_name} (1 tahun)",
                'amount' => $amount,
            ]);

            $invoice->recalculateFromItems();
            $domain->update(['renewal_invoice_id' => $invoice->id]);

            return $invoice->fresh();
        });
    }

    private function createInvoice(int $clientId, float $amount, $dueDate): Invoice
    {
        return Invoice::create([
            'client_id' => $clientId,
            'amount' => $amount,
            'tax' => 0,
            'discount' => 0,
            'status' => 'unpaid',
            'issue_date' => now(),
            'due_date' => $dueDate ?: now()->addDays(7),
        ]);
    }
}
