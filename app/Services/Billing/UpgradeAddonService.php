<?php

namespace App\Services\Billing;

use App\Models\Addon;
use App\Models\HostingAccount;
use App\Models\HostingAccountAddon;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpgradeAddonService
{
    /**
     * Membuat invoice upgrade secara atomik. Baris hosting dikunci agar dua
     * request bersamaan tidak menghasilkan dua invoice upgrade.
     */
    public function createUpgradeInvoice(HostingAccount $service, Product $newProduct): Invoice
    {
        return DB::transaction(function () use ($service, $newProduct) {
            $hosting = HostingAccount::query()->lockForUpdate()->findOrFail($service->id);

            if ($hosting->status !== 'active') {
                throw new RuntimeException('Upgrade hanya bisa dilakukan untuk layanan yang sedang aktif.');
            }

            if ($hosting->pending_upgrade_invoice_id) {
                $existing = Invoice::find($hosting->pending_upgrade_invoice_id);
                if ($existing && in_array($existing->status, ['unpaid', 'overdue'], true)) {
                    return $existing;
                }

                $hosting->update([
                    'pending_upgrade_product_id' => null,
                    'pending_upgrade_invoice_id' => null,
                ]);
            }

            $eligible = $hosting->upgradeEligibleProducts()->firstWhere('id', $newProduct->id);
            if (! $eligible) {
                throw new RuntimeException('Paket yang dipilih tidak tersedia untuk upgrade dari paket Anda saat ini.');
            }

            $amount = $hosting->prorateUpgrade($eligible);
            if ($amount <= 0) {
                throw new RuntimeException('Biaya upgrade tidak valid.');
            }

            $invoice = Invoice::create([
                'client_id' => $hosting->client_id,
                'amount' => $amount,
                'tax' => 0,
                'discount' => 0,
                'status' => 'unpaid',
                'issue_date' => now(),
                'due_date' => now()->addDays(3),
                'notes' => 'Invoice upgrade hosting.',
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => "Upgrade {$hosting->domain}: {$hosting->product?->name} → {$eligible->name} (prorata sisa siklus)",
                'amount' => $amount,
            ]);

            $hosting->update([
                'pending_upgrade_product_id' => $eligible->id,
                'pending_upgrade_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }

    /**
     * Membuat invoice addon secara atomik dan mencegah addon yang sama
     * dipasang dua kali ketika dua request masuk bersamaan.
     */
    public function createAddonInvoice(HostingAccount $service, Addon $addon): Invoice
    {
        return DB::transaction(function () use ($service, $addon) {
            $hosting = HostingAccount::query()->lockForUpdate()->findOrFail($service->id);
            $catalogAddon = Addon::query()->whereKey($addon->id)->where('is_active', true)->first();

            if (! $catalogAddon) {
                throw new RuntimeException('Addon tidak tersedia.');
            }

            $existing = HostingAccountAddon::query()
                ->where('hosting_account_id', $hosting->id)
                ->where('addon_id', $catalogAddon->id)
                ->whereIn('status', ['pending_payment', 'active'])
                ->first();

            if ($existing) {
                if ($existing->status === 'pending_payment' && $existing->invoice_id) {
                    $existingInvoice = Invoice::find($existing->invoice_id);
                    if ($existingInvoice && in_array($existingInvoice->status, ['unpaid', 'overdue'], true)) {
                        return $existingInvoice;
                    }
                }

                throw new RuntimeException('Addon ini sudah terpasang atau sedang menunggu pembayaran.');
            }

            $price = $catalogAddon->priceForCycle($hosting->billing_cycle);
            if ($price === null) {
                throw new RuntimeException('Addon ini tidak tersedia untuk siklus tagihan layanan Anda.');
            }

            $amount = $hosting->prorateAddon($catalogAddon);
            if ($amount <= 0) {
                throw new RuntimeException('Biaya addon tidak valid.');
            }

            $invoice = Invoice::create([
                'client_id' => $hosting->client_id,
                'amount' => $amount,
                'tax' => 0,
                'discount' => 0,
                'status' => 'unpaid',
                'issue_date' => now(),
                'due_date' => now()->addDays(3),
                'notes' => 'Invoice addon hosting.',
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => "Addon {$catalogAddon->name} — {$hosting->domain} (prorata sisa siklus)",
                'amount' => $amount,
            ]);

            HostingAccountAddon::create([
                'hosting_account_id' => $hosting->id,
                'addon_id' => $catalogAddon->id,
                'name' => $catalogAddon->name,
                'price' => $price,
                'status' => 'pending_payment',
                'invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }
}
