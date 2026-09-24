<?php

namespace App\Console\Commands;

use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Enums\OrderStatus;
use App\Services\Hosting\HostingPanelFactory;
use App\Services\Provisioning\ProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Jaring pengaman — kalau pemicu otomatis provisioning (lewat event
 * Invoice::updated ketika status berubah jadi 'paid') pernah gagal
 * terpicu karena alasan apa pun (mis. invoice sempat ditandai lunas dua
 * kali, sehingga wasChanged('status') tidak lagi true di percobaan
 * kedua), perintah ini menemukan & memperbaikinya sendiri secara
 * berkala -- tanpa perlu admin sadar atau klik tombol manual.
 *
 * SENGAJA tidak asal panggil createAccount() lagi untuk hosting yang
 * "kelihatan belum diprovisikan" -- itu bisa saja SEBENARNYA sudah ada
 * di server (skenario yang pernah terjadi), dan mencoba buat lagi cuma
 * akan ditolak WHM. Dicek dulu ke server sebelum memutuskan.
 */
class ReconcileProvisioning extends Command
{
    protected $signature = 'lumora:reconcile-provisioning {--dry : Cuma tampilkan yang ditemukan, tanpa memperbaiki}';

    protected $description = 'Cari invoice lunas yang layanannya belum aktif, lalu perbaiki otomatis.';

    public function handle(ProvisioningService $provisioning): int
    {
        ob_start();
        $result = $this->handleJob($provisioning);
        $output = ob_get_clean();
        echo $output;

        \App\Models\CronJob::recordExecution('lumora:reconcile-provisioning', $result === self::SUCCESS, $output);

        return $result;
    }

    private function handleJob(ProvisioningService $provisioning): int
    {
        $dry = (bool) $this->option('dry');
        $fixedCount = 0;

        // Cuma invoice yang genuinely masih ada order/layanan yang belum
        // beres -- bukan SEMUA invoice lunas (yang jumlahnya bisa ribuan
        // seiring waktu, mahal diperiksa satu-satu tiap kali cron jalan).
        $stuckInvoices = Invoice::where('status', 'paid')
            ->whereHas('items.order', function ($q) {
                $q->where(function ($q) {
                    $q->whereIn('status', [
                        OrderStatus::Paid->value,
                        OrderStatus::Provisioning->value,
                        OrderStatus::Failed->value,
                    ])
                      ->orWhereHas('hostingAccount', fn ($h) => $h->whereIn('provision_status', ['failed', 'manual']))
                      ->orWhereHas('domain', fn ($d) => $d->whereIn('provision_status', ['failed', 'needs_documents', 'needs_eligibility']));
                });
            })
            ->with(['items.order.hostingAccount.serverModel', 'items.order.domain.registrar'])
            ->get();

        if ($stuckInvoices->isEmpty()) {
            $this->info('Tidak ada invoice lunas dengan order yang menggantung.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$stuckInvoices->count()} invoice lunas dengan provisioning yang belum selesai:");

        foreach ($stuckInvoices as $invoice) {
            foreach ($invoice->items as $item) {
                $order = $item->order;

                if (! $order) { continue; }
                $needs = ($order->order_type === 'hosting' && $order->hostingAccount && $order->hostingAccount->provision_status !== 'provisioned')
                    || ($order->order_type === 'domain' && $order->domain && ! in_array($order->domain->provision_status, ['registered', 'manual'], true));
                if (! $needs) continue;

                $this->line("  - {$invoice->invoice_number}: Order #{$order->id} ({$order->order_type})");

                if ($dry) {
                    continue;
                }

                if ($order->order_type === 'hosting' && $order->hostingAccount) {
                    $fixed = $this->reconcileHosting($order->hostingAccount);
                } elseif ($order->order_type === 'domain' && $order->domain) {
                    $provisioning->provisionInvoice($invoice);
                    $order->refresh();
                    $fixed = $order->status === \App\Enums\OrderStatus::Completed;
                } else {
                    $fixed = false;
                }

                if ($fixed) {
                    $fixedCount++;
                    $this->info('    -> diperbaiki');
                } else {
                    $this->warn('    -> masih belum beres, perlu dicek manual admin');
                }
            }
        }

        if ($dry) {
            $this->newLine();
            $this->info('Mode simulasi -- tidak ada yang diperbaiki.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Selesai -- {$fixedCount} layanan berhasil diperbaiki otomatis.");

        return self::SUCCESS;
    }

    private function reconcileHosting(HostingAccount $account): bool
    {
        if (! $account->serverModel) {
            return false;
        }

        $service = HostingPanelFactory::make($account->serverModel);

        if (method_exists($service, 'listAccounts')) {
            $result = $service->listAccounts();

            if ($result['success']) {
                $match = collect($result['accounts'])->firstWhere('domain', $account->domain);

                if ($match) {
                    $account->update([
                        'username'          => $match['username'],
                        'status'            => $match['suspended'] ? 'suspended' : 'active',
                        'provision_status'  => 'provisioned',
                        'provision_message' => 'Disinkronkan otomatis oleh lumora:reconcile-provisioning.',
                    ]);

                    $account->orders()
                        ->where('order_type', 'hosting')
                        ->whereIn('status', [
                            OrderStatus::Paid->value,
                            OrderStatus::Provisioning->value,
                            OrderStatus::Failed->value,
                        ])
                        ->get()
                        ->each(function ($order) {
                            if ($order->status === OrderStatus::Failed) {
                                $order->markProvisioning('Provider sudah memiliki akun; status direkonsiliasi.');
                            }
                            $order->markCompleted('Provider sudah memiliki akun; status direkonsiliasi.');
                        });

                    Log::info('reconcile-provisioning: hosting disinkronkan', ['hosting_account_id' => $account->id]);

                    return true;
                }
            }
        }

        if ($account->provision_status !== 'provisioned') {
            $order = $account->orders()->where('order_type', 'hosting')->latest('id')->first();
            $invoiceItem = $order ? \App\Models\InvoiceItem::where('order_id', $order->id)->first() : null;

            if ($invoiceItem) {
                app(ProvisioningService::class)->provisionInvoice($invoiceItem->invoice);
                $account->refresh();
                $order->refresh();

                Log::info('reconcile-provisioning: hosting dicoba provisikan ulang', ['hosting_account_id' => $account->id]);

                return $account->provision_status === 'provisioned' && $order->status === \App\Enums\OrderStatus::Completed;
            }
        }

        return false;
    }
}
