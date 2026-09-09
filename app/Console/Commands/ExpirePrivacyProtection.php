<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\Domain\DomainRegistrarFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ID Protection punya masa berlaku 1 tahun sendiri, terpisah dari masa
 * domain. Kalau klien tidak memperpanjang, perlindungannya harus
 * benar-benar DIMATIKAN di registrar — bukan sekadar dianggap habis di
 * database kita.
 *
 * Kalau ini tidak dijalankan, kita akan terus ditagih registrar untuk
 * perlindungan yang sudah tidak dibayar klien.
 */
class ExpirePrivacyProtection extends Command
{
    protected $signature = 'lumora:expire-privacy
                            {--dry : Hanya tampilkan yang akan diproses, tanpa benar-benar mengubah apa pun}';

    protected $description = 'Matikan ID Protection yang masa berlakunya sudah habis dan tidak diperpanjang';

    
    public function handle(): int
    {
        ob_start();
        $result = $this->handleJob();
        $output = ob_get_clean();
        echo $output;

        \App\Models\CronJob::recordExecution('lumora:expire-privacy', $result === self::SUCCESS, $output);

        return $result;
    }

    private function handleJob(): int
    {
        $dry = $this->option('dry');

        $expired = Domain::where('whois_privacy', true)
            ->whereNotNull('privacy_expires_at')
            ->whereDate('privacy_expires_at', '<', now())
            ->whereNull('privacy_invoice_id') // sedang menunggu bayar perpanjangan -> jangan dimatikan dulu
            ->with('registrar')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('Tidak ada ID Protection yang kedaluwarsa.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$expired->count()} domain dengan ID Protection kedaluwarsa.");

        $count = 0;

        foreach ($expired as $domain) {
            $this->line("  - {$domain->domain_name} (habis {$domain->privacy_expires_at->format('d M Y')})");

            if ($dry) {
                continue;
            }

            if (! $domain->registrar) {
                // Domain manual tanpa registrar — cukup tandai di sistem.
                $domain->update(['whois_privacy' => false]);
                $count++;

                continue;
            }

            try {
                $service = DomainRegistrarFactory::make($domain->registrar);

                if (method_exists($service, 'disablePrivacyProtection')) {
                    $result = $service->disablePrivacyProtection($domain->domain_name);

                    if (! $result['success']) {
                        $this->error("      gagal di registrar: {$result['message']}");
                        Log::warning('Gagal mematikan ID Protection kedaluwarsa: ' . $result['message'], [
                            'domain_id' => $domain->id,
                        ]);

                        continue;
                    }
                }

                $domain->update(['whois_privacy' => false]);
                $count++;
            } catch (Throwable $e) {
                $this->error("      error: {$e->getMessage()}");
                Log::error('Error mematikan ID Protection kedaluwarsa: ' . $e->getMessage(), [
                    'domain_id' => $domain->id,
                ]);
            }
        }

        $this->newLine();
        $this->info($dry ? 'Mode simulasi — tidak ada yang diubah.' : "Selesai. {$count} ID Protection dimatikan.");

        return self::SUCCESS;
    }
}
