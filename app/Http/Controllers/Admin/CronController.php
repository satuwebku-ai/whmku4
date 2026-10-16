<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CronJob;
use App\Models\Setting;
use App\Console\Commands\RunCron;
use App\Services\Hosting\CpanelCronService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Throwable;

class CronController extends Controller
{

    public function indexBootstrap(CpanelCronService $cpanel): View
    {
        return view('admin.cron.index', $this->indexData($cpanel));
    }

    private function indexData(CpanelCronService $cpanel): array
    {
        // Tugas baru dari update aplikasi otomatis muncul di sini.
        CronJob::syncBuiltIn();

        // Baris dari job yang sudah dikeluarkan dari registry tetap disimpan
        // sebagai riwayat, tetapi tidak ditampilkan atau dapat diatur lagi.
        $jobs = CronJob::whereIn('key', array_keys(CronJob::BUILT_IN))
            ->orderBy('name')
            ->get();

        return [
            'jobs' => $jobs,
            'cronLine' => $cpanel->cronLine(),
            'cpanelConfigured' => $cpanel->isConfigured(),
            // Kalau tidak ada tugas yang pernah jalan, hampir pasti cron
            // di server belum dipasang — itu kesalahan paling sering.
            'neverRan' => $jobs->whereNotNull('last_run_at')->isEmpty(),
            'queue' => app(\App\Services\QueueDrainer::class)->status(),
        ];
    }

    /**
     * Simpan perubahan jadwal/status semua tugas sekaligus.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jobs' => ['required', 'array'],
            'jobs.*.interval_minutes' => ['required', 'integer', 'in:' . implode(',', array_keys(CronJob::INTERVALS))],
        ]);

        $enabled = array_map('intval', (array) $request->input('enabled', []));
        $changed = 0;

        foreach ($data['jobs'] as $id => $row) {
            $job = CronJob::find((int) $id);

            if (! $job) {
                continue;
            }

            $intervalBaru = (int) $row['interval_minutes'];
            $aktifBaru = in_array((int) $id, $enabled, true);

            $job->fill([
                'interval_minutes' => $intervalBaru,
                'is_enabled' => $aktifBaru,
            ]);

            // Jadwal berikutnya dihitung ulang kalau intervalnya berubah,
            // supaya perubahan langsung terasa tanpa menunggu siklus lama.
            if ($job->isDirty('interval_minutes')) {
                $job->next_run_at = now()->addMinutes($intervalBaru);
            }

            if ($job->isDirty()) {
                $job->save();
                $changed++;
            }
        }

        return back()->with(
            $changed ? 'success' : 'info',
            $changed ? "{$changed} tugas diperbarui." : 'Tidak ada perubahan.'
        );
    }

    /**
     * Jalankan satu tugas sekarang, tanpa menunggu jadwal.
     */
    public function runNow(CronJob $job): RedirectResponse
    {
        try {
            $runCountBefore = (int) $job->run_count;
            $exitCode = Artisan::call('lumora:cron', ['--job' => $job->key]);
            $output = trim(Artisan::output());

            $job->refresh();

            if (str_contains($output, RunCron::SKIPPED_MARKER)) {
                return back()->with('info', "Tugas {$job->name} sedang berjalan di proses lain; permintaan ini dilewati.");
            }

            if ($exitCode !== 0 || $job->last_status === 'failed') {
                $detail = $output !== '' ? $output : $job->last_output;

                return back()->with(
                    'error',
                    "Tugas {$job->name} gagal dijalankan." . ($detail ? ' ' . $detail : '')
                );
            }

            if ((int) $job->run_count <= $runCountBefore) {
                return back()->with('error', "Tugas {$job->name} tidak mencatat eksekusi baru; hasil sebelumnya tidak dianggap sukses.");
            }

            return back()->with(
                'success',
                "Tugas {$job->name} selesai dijalankan."
            );
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menjalankan tugas: ' . $e->getMessage());
        }
    }

    // ── Pengaturan & integrasi cPanel ────────────────────────────────

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cpanel_host'  => ['nullable', 'string', 'max:255'],
            'cpanel_port'  => ['nullable', 'integer', 'min:1', 'max:65535'],
            'cpanel_user'  => ['nullable', 'string', 'max:100'],
            'cpanel_token' => ['nullable', 'string', 'max:500'],
            'cpanel_php_path' => ['nullable', 'string', 'max:255'],
            'cpanel_verify_ssl' => ['nullable', 'boolean'],

            'auto_suspend_enabled' => ['nullable', 'boolean'],
            'suspend_grace_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'checkout_cancel_grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
        ]);

        $data['cpanel_verify_ssl'] = $request->boolean('cpanel_verify_ssl') ? '1' : '0';
        $data['auto_suspend_enabled'] = $request->boolean('auto_suspend_enabled') ? '1' : '0';
        // Form cPanel dan form suspend memakai endpoint yang sama. Jangan
        // mengubah toleransi checkout ketika field ini tidak ikut dikirim.
        if ($request->has('checkout_cancel_grace_days')) {
            $data['checkout_cancel_grace_days'] = (string) $data['checkout_cancel_grace_days'];
        }

        // Token kosong = tidak diganti.
        if (blank($data['cpanel_token'] ?? null)) {
            unset($data['cpanel_token']);
        }

        Setting::putMany($data, 'cron');

        return back()->with('success', 'Pengaturan tersimpan.');
    }

    public function testCpanel(CpanelCronService $cpanel): RedirectResponse
    {
        $result = $cpanel->testConnection();

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Pasang baris cron ke cPanel secara otomatis.
     */
    public function installCpanel(CpanelCronService $cpanel): RedirectResponse
    {
        $result = $cpanel->install();

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
