<?php

namespace App\Console\Commands;

use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Hosting\HostingPanelFactory;
use Illuminate\Console\Command;
use Throwable;

/**
 * Suspend layanan hosting yang tagihannya menunggak melebihi batas.
 *
 * Sengaja TIDAK menghapus apa pun — hanya suspend, yang bisa dibatalkan.
 * Penghentian permanen tetap keputusan manusia.
 */
class SuspendOverdue extends Command
{
    protected $signature = 'lumora:suspend-overdue
                            {--dry : Tampilkan yang akan disuspend tanpa benar-benar melakukannya}';

    protected $description = 'Suspend layanan dengan tagihan menunggak melebihi batas toleransi';

    public function handle(): int
    {
        if (Setting::get('auto_suspend', '0') !== '1') {
            $this->warn('Auto-suspend dinonaktifkan di Pengaturan → Cron Jobs.');

            return self::SUCCESS;
        }

        $graceDays = (int) Setting::get('suspend_grace_days', 7);
        $dry = $this->option('dry');
        $batas = now()->subDays($graceDays)->toDateString();

        $this->info("Batas toleransi: {$graceDays} hari setelah jatuh tempo.");

        // Klien dengan invoice menunggak melewati masa toleransi.
        $clientIds = Invoice::whereIn('status', ['unpaid', 'overdue'])
            ->whereDate('due_date', '<', $batas)
            ->pluck('client_id')
            ->unique();

        if ($clientIds->isEmpty()) {
            $this->info('Tidak ada tagihan yang melewati batas toleransi.');

            return self::SUCCESS;
        }

        $accounts = HostingAccount::whereIn('client_id', $clientIds)
            ->where('status', 'active')
            ->with(['client', 'serverModel'])
            ->get();

        $suspended = 0;

        foreach ($accounts as $account) {
            $this->line("  {$account->domain} ({$account->client->name})");

            if ($dry) {
                $suspended++;
                continue;
            }

            try {
                // Akun tanpa server hanya diubah statusnya; tidak ada API
                // yang bisa dipanggil untuknya.
                if ($account->serverModel && $account->username) {
                    $result = HostingPanelFactory::make($account->serverModel)
                        ->suspendAccount($account->username, 'Tagihan menunggak');

                    if (! $result['success']) {
                        $this->error("      gagal: {$result['message']}");
                        continue;
                    }
                }

                $account->update([
                    'status' => 'suspended',
                    'provision_message' => 'Disuspend otomatis karena tagihan menunggak.',
                ]);

                $suspended++;
            } catch (Throwable $e) {
                $this->error('      error: ' . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info($dry
            ? "Simulasi: {$suspended} layanan akan disuspend."
            : "{$suspended} layanan disuspend.");

        return self::SUCCESS;
    }
}
