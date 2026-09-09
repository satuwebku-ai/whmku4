<?php

namespace App\Console\Commands;

use App\Models\CronJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Menjalankan semua tugas terjadwal yang sudah waktunya.
 *
 * Ini satu-satunya perintah yang perlu dipasang di cron server. Jadwal
 * tiap tugas diatur dari panel admin, bukan dari baris cron — supaya
 * mengubah jadwal tidak perlu akses SSH atau cPanel lagi.
 */
class RunCron extends Command
{
    protected $signature = 'lumora:cron
                            {--job= : Jalankan satu tugas tertentu berdasarkan key, abaikan jadwal}
                            {--force : Jalankan meski belum waktunya}';

    protected $description = 'Jalankan tugas terjadwal yang sudah waktunya';

    public function handle(): int
    {
        CronJob::syncBuiltIn();

        $jobs = $this->option('job')
            ? CronJob::where('key', $this->option('job'))->get()
            : ($this->option('force') ? CronJob::where('is_enabled', true)->get() : CronJob::due()->get());

        if ($jobs->isEmpty()) {
            $this->line('Tidak ada tugas yang perlu dijalankan.');

            return self::SUCCESS;
        }

        foreach ($jobs as $job) {
            $this->runJob($job);
        }

        return self::SUCCESS;
    }

    private function runJob(CronJob $job): void
    {
        $this->line("Menjalankan: {$job->name} ({$job->command})");

        $job->update(['last_status' => 'running']);
        $mulai = microtime(true);

        try {
            // Output ditangkap supaya bisa ditampilkan di panel admin —
            // tanpa ini, kegagalan tugas hanya terlihat di log server.
            Artisan::call($job->command);
            $output = trim(Artisan::output());

            $job->update([
                'last_status' => 'success',
                'last_output' => mb_substr($output, 0, 2000),
                'last_run_at' => now(),
                'next_run_at' => now()->addMinutes($job->interval_minutes),
                'last_duration_ms' => (int) ((microtime(true) - $mulai) * 1000),
                'run_count' => $job->run_count + 1,
            ]);

            $this->info('  selesai');
        } catch (Throwable $e) {
            // Kegagalan satu tugas tidak boleh menghentikan tugas lain,
            // jadi errornya dicatat lalu proses lanjut.
            $job->update([
                'last_status' => 'failed',
                'last_output' => mb_substr($e->getMessage(), 0, 2000),
                'last_run_at' => now(),
                // Tetap dijadwalkan ulang supaya gangguan sesaat bisa pulih
                // sendiri tanpa perlu diutak-atik manual.
                'next_run_at' => now()->addMinutes($job->interval_minutes),
                'last_duration_ms' => (int) ((microtime(true) - $mulai) * 1000),
                'run_count' => $job->run_count + 1,
            ]);

            $this->error('  gagal: ' . $e->getMessage());
        }
    }
}
