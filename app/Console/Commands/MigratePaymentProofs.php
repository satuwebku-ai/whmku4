<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pindahkan bukti transfer lama dari disk public ke disk private.
 *
 * Upload baru sudah memakai disk local. Command ini disediakan untuk
 * deployment yang sebelumnya menyimpan payment-proofs di public/storage.
 */
class MigratePaymentProofs extends Command
{
    protected $signature = 'lumora:migrate-payment-proofs {--dry-run : Hanya tampilkan file yang akan dipindahkan}';

    protected $description = 'Pindahkan bukti transfer dari storage public ke storage private';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $moved = 0;
        $missing = 0;
        $failed = 0;

        Payment::query()
            ->whereNotNull('proof_path')
            ->select(['id', 'proof_path'])
            ->chunkById(100, function ($payments) use (
                $public,
                $private,
                &$moved,
                &$missing,
                &$failed
            ): void {
                foreach ($payments as $payment) {
                    $path = (string) $payment->proof_path;

                    if ($private->exists($path)) {
                        continue;
                    }

                    if (! $public->exists($path)) {
                        $missing++;
                        $this->warn("Tidak ditemukan: {$path} (payment #{$payment->id})");
                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("Akan dipindahkan: {$path}");
                        continue;
                    }

                    try {
                        $contents = $public->get($path);
                        if (! $private->put($path, $contents)) {
                            throw new \RuntimeException('Gagal menulis ke disk private.');
                        }

                        $public->delete($path);
                        $moved++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->error("Gagal memindahkan {$path}: {$e->getMessage()}");
                    }
                }
            });

        $this->info($this->option('dry-run')
            ? 'Dry-run selesai.'
            : "Selesai. Dipindahkan: {$moved}, tidak ditemukan: {$missing}, gagal: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}