<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Memproses antrean (tabel `jobs`) dari cron `lumora:cron`.
 *
 * Kenapa perlu: notifikasi email/WhatsApp lewat NotificationService dan
 * job ProcessPaidInvoice (aktivasi layanan setelah bayar) masuk antrean
 * database. Tanpa proses `queue:work`, semuanya menumpuk dan tidak pernah
 * terkirim. Banyak hosting (cPanel) tidak menyediakan daemon/Supervisor,
 * jadi antrean dikuras tiap menit oleh cron yang sama dengan tugas lain.
 *
 * Worker dijalankan di PROSES TERPISAH: worker membunuh prosesnya sendiri
 * (SIGKILL) kalau satu job melewati --timeout, dan itu tidak boleh ikut
 * mematikan lumora:cron.
 */
class QueueDrainer
{
    private const LOCK = 'lumora:queue-drain';

    /** Detik maksimal worker mengambil job baru; sisanya untuk job yang sedang jalan. */
    private const MAX_TIME = 45;

    /** Job yang sudah siap diproses tapi belum tersentuh sekian detik dianggap tertahan. */
    private const STUCK_AFTER = 600;

    public function enabled(): bool
    {
        return config('queue.default') === 'database'
            && (bool) config('queue.drain_in_cron', true);
    }

    /**
     * @return array{ran: bool, message: string}
     */
    public function drain(): array
    {
        if (! $this->enabled()) {
            return ['ran' => false, 'message' => 'dilewati (antrean bukan database, atau QUEUE_DRAIN_IN_CRON=false)'];
        }

        if (! Schema::hasTable('jobs')) {
            return ['ran' => false, 'message' => 'dilewati (tabel jobs belum ada, jalankan php artisan migrate)'];
        }

        $lock = Cache::lock(self::LOCK, 200);

        if (! $lock->get()) {
            return ['ran' => false, 'message' => 'dilewati (pemrosesan antrean sebelumnya masih berjalan)'];
        }

        try {
            return $this->runWorker();
        } catch (Throwable $e) {
            Log::warning('Antrean: gagal memproses dari cron — ' . $e->getMessage());

            return ['ran' => false, 'message' => 'gagal: ' . $e->getMessage()];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return array{ran: bool, message: string}
     */
    private function runWorker(): array
    {
        $options = [
            // Job pembayaran (ProcessPaidInvoice) masuk antrean 'billing'.
            // Tanpa --queue, worker hanya membaca 'default' dan saldo/layanan
            // tidak pernah diproses setelah bayar.
            '--queue=billing,default',
            '--stop-when-empty',
            '--max-time=' . self::MAX_TIME,
            '--sleep=1',
            '--tries=3',
            '--timeout=120',
            '--no-interaction',
        ];

        try {
            $php = \App\Models\Setting::get('cpanel_php_path') ?: (new PhpExecutableFinder())->find(false) ?: 'php';

            $process = new Process([$php, base_path('artisan'), 'queue:work', ...$options], base_path());
            $process->setTimeout(170);
            $process->run();

            $out = trim($process->getOutput() . $process->getErrorOutput());

            return [
                'ran' => true,
                'message' => $process->isSuccessful()
                    ? 'selesai' . ($out !== '' ? ' (' . substr_count($out, 'DONE') . ' job)' : '')
                    : 'berhenti dengan kode ' . $process->getExitCode() . ($out !== '' ? ': ' . mb_substr($out, -300) : ''),
            ];
        } catch (Throwable $e) {
            // proc_open dimatikan di sebagian hosting: jalankan di proses ini.
            // --timeout=0 mematikan SIGKILL otomatis supaya tidak ikut
            // membunuh lumora:cron.
            Log::info('Antrean: proses terpisah tidak tersedia, memakai proses ini — ' . $e->getMessage());

            Artisan::call('queue:work', [
                '--queue' => 'billing,default',
                '--stop-when-empty' => true,
                '--max-time' => self::MAX_TIME,
                '--sleep' => 1,
                '--tries' => 3,
                '--timeout' => 0,
            ]);

            return ['ran' => true, 'message' => 'selesai (dalam proses cron)'];
        }
    }

    /**
     * Kondisi antrean untuk panel admin dan checklist setup.
     *
     * @return array{applicable: bool, driver: string, drain_enabled: bool, pending: int, stuck: int, oldest_minutes: int, failed_recent: int}
     */
    public function status(): array
    {
        $driver = (string) config('queue.default');
        $base = ['applicable' => false, 'driver' => $driver, 'drain_enabled' => $this->enabled(),
            'pending' => 0, 'stuck' => 0, 'oldest_minutes' => 0, 'failed_recent' => 0];

        if ($driver !== 'database' || ! Schema::hasTable('jobs')) {
            return $base;
        }

        $now = time();
        $ready = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<=', $now);

        $oldest = (clone $ready)->min('available_at');

        return array_merge($base, [
            'applicable' => true,
            'pending' => (int) DB::table('jobs')->whereNull('reserved_at')->count(),
            'stuck' => (int) (clone $ready)->where('available_at', '<=', $now - self::STUCK_AFTER)->count(),
            'oldest_minutes' => $oldest ? (int) floor(($now - (int) $oldest) / 60) : 0,
            'failed_recent' => Schema::hasTable('failed_jobs')
                ? (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count()
                : 0,
        ]);
    }
}