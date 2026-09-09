<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CronJob;
use App\Models\Setting;
use App\Services\Hosting\CpanelCronService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Throwable;

class CronController extends Controller
{
    public function index(CpanelCronService $cpanel): View
    {
        return view('admin.cron.index', $this->indexData($cpanel));
    }

    public function indexBootstrap(CpanelCronService $cpanel): View
    {
        return view('admin.cron.index', $this->indexData($cpanel));
    }

    private function indexData(CpanelCronService $cpanel): array
    {
        // Tugas baru dari update aplikasi otomatis muncul di sini.
        CronJob::syncBuiltIn();

        $jobs = CronJob::orderBy('name')->get();

        return [
            'jobs' => $jobs,
            'cronLine' => $cpanel->cronLine(),
            'cpanelConfigured' => $cpanel->isConfigured(),
            // Kalau tidak ada tugas yang pernah jalan, hampir pasti cron
            // di server belum dipasang — itu kesalahan paling sering.
            'neverRan' => $jobs->whereNotNull('last_run_at')->isEmpty(),
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
            Artisan::call('lumora:cron', ['--job' => $job->key]);

            $job->refresh();

            return back()->with(
                $job->last_status === 'failed' ? 'error' : 'success',
                $job->last_status === 'failed'
                    ? "Tugas {$job->name} gagal: " . $job->last_output
                    : "Tugas {$job->name} selesai dijalankan."
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

            'auto_suspend' => ['nullable', 'boolean'],
            'suspend_grace_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $data['cpanel_verify_ssl'] = $request->boolean('cpanel_verify_ssl') ? '1' : '0';
        $data['auto_suspend'] = $request->boolean('auto_suspend') ? '1' : '0';

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
