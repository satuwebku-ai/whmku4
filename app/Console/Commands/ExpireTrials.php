<?php

namespace App\Console\Commands;

use App\Models\HostingAccount;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Services\Hosting\HostingPanelFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sengaja TERPISAH dari lumora:suspend-overdue -- perintah itu cuma
 * menangani invoice PERPANJANGAN (renewal_invoice_id), bukan invoice
 * pembelian PERTAMA. Hosting trial belum punya renewal_invoice_id sama
 * sekali (baru dapat itu setelah lolos satu periode penagihan normal),
 * jadi butuh pengecekan sendiri.
 */
class ExpireTrials extends Command
{
    protected $signature = 'lumora:expire-trials {--dry : Cuma tampilkan yang akan disuspend, tanpa benar-benar menyuspend}';

    protected $description = 'Suspend hosting trial yang masa percobaannya habis tapi belum dibayar.';

    public function handle(): int
    {
        ob_start();
        $result = $this->handleJob();
        $output = ob_get_clean();
        echo $output;

        \App\Models\CronJob::recordExecution('lumora:expire-trials', $result === self::SUCCESS, $output);

        return $result;
    }

    private function handleJob(): int
    {
        $dry = (bool) $this->option('dry');

        $accounts = HostingAccount::with(['client', 'serverModel'])
            ->where('status', 'active')
            ->whereNotNull('trial_expires_at')
            ->where('trial_expires_at', '<', now())
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('Tidak ada trial yang sudah habis masa berlakunya.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($accounts as $hosting) {
            // Cuma disuspend kalau invoice pertamanya MASIH belum lunas —
            // kalau klien sudah bayar sebelum trial habis, provisionInvoice()
            // pasti sudah jalan normal dan ini seharusnya tidak lagi
            // relevan, tapi dicek ulang di sini demi keamanan.
            $order = $hosting->orders()->where('order_type', 'hosting')->latest('id')->first();
            $invoiceItem = $order ? InvoiceItem::where('order_id', $order->id)->first() : null;
            $invoice = $invoiceItem?->invoice;

            if (! $invoice || $invoice->status === 'paid') {
                continue;
            }

            $this->line("  [Trial habis] {$hosting->domain} — {$hosting->client?->email}");

            if ($dry) {
                $count++;

                continue;
            }

            try {
                $this->suspendOne($hosting, $invoice->invoice_number);
                $count++;
            } catch (Throwable $e) {
                $this->error('        gagal: ' . $e->getMessage());
                Log::error('Auto-suspend trial gagal: ' . $e->getMessage(), ['hosting_account_id' => $hosting->id]);
            }
        }

        if ($dry) {
            $this->newLine();
            $this->info("Mode simulasi -- {$count} trial akan disuspend.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Selesai -- {$count} trial disuspend karena belum dibayar.");

        return self::SUCCESS;
    }

    private function suspendOne(HostingAccount $hosting, string $invoiceNumber): void
    {
        $message = "Trial berakhir tanpa pembayaran (invoice {$invoiceNumber}).";

        if ($hosting->serverModel && $hosting->username) {
            $result = HostingPanelFactory::make($hosting->serverModel)->suspendAccount(
                $hosting->username,
                'Masa percobaan berakhir, invoice belum dibayar: ' . $invoiceNumber
            );

            if (! $result['success']) {
                Log::warning('Suspend trial API gagal, status database tetap diperbarui: ' . $result['message'], [
                    'hosting_account_id' => $hosting->id,
                ]);
            }

            $message = $result['message'] ?? $message;
        }

        $hosting->update([
            'status' => 'suspended',
            'provision_message' => $message,
        ]);
    }
}
