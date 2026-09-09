<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function index(): View
    {
        return view('admin.backups.index', $this->indexData());
    }

    public function indexBootstrap(): View
    {
        return view('admin.backups.index', $this->indexData());
    }

    private function indexData(): array
    {
        $dir = storage_path('app/backups');
        $files = is_dir($dir) ? glob("{$dir}/lumora-backup_*.zip") : [];

        $backups = collect($files)
            ->map(fn ($path) => [
                'name' => basename($path),
                'size' => round(filesize($path) / 1024 / 1024, 2),
                'created_at' => \Carbon\Carbon::createFromTimestamp(filemtime($path)),
            ])
            ->sortByDesc('created_at')
            ->values();

        return [
            'backups' => $backups,
            'retention' => (int) Setting::get('backup_retention', 7),
            'enabled' => Setting::get('backup_enabled', '1') === '1',
            'gdrive' => [
                'enabled' => Setting::get('backup_gdrive_enabled') === '1',
                'client_id' => Setting::get('backup_gdrive_client_id'),
                'client_secret' => Setting::get('backup_gdrive_client_secret'),
                'refresh_token' => Setting::get('backup_gdrive_refresh_token'),
                'folder' => Setting::get('backup_gdrive_folder', 'Lumora Backup'),
            ],
        ];
    }

    public function updateGoogleDrive(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'backup_gdrive_client_id' => ['nullable', 'string'],
            'backup_gdrive_client_secret' => ['nullable', 'string'],
            'backup_gdrive_refresh_token' => ['nullable', 'string'],
            'backup_gdrive_folder' => ['nullable', 'string', 'max:100'],
        ]);

        Setting::put('backup_gdrive_enabled', $request->boolean('backup_gdrive_enabled') ? '1' : '0', 'general');
        Setting::put('backup_gdrive_client_id', $data['backup_gdrive_client_id'] ?? '', 'general');
        // Client Secret & Refresh Token itu kredensial sensitif -- dua
        // ini yang mengizinkan akses berkelanjutan ke Google Drive akun
        // itu, jadi disimpan terenkripsi (encrypted=true), sama seperti
        // kredensial server/gateway pembayaran di tempat lain.
        Setting::put('backup_gdrive_client_secret', $data['backup_gdrive_client_secret'] ?? '', 'general', true);
        Setting::put('backup_gdrive_refresh_token', $data['backup_gdrive_refresh_token'] ?? '', 'general', true);
        Setting::put('backup_gdrive_folder', $data['backup_gdrive_folder'] ?: 'Lumora Backup', 'general');

        return back()->with('success', 'Pengaturan Google Drive disimpan.');
    }

    public function testGoogleDrive(): RedirectResponse
    {
        $clientId = Setting::get('backup_gdrive_client_id');
        $clientSecret = Setting::get('backup_gdrive_client_secret');
        $refreshToken = Setting::get('backup_gdrive_refresh_token');

        if (blank($clientId) || blank($clientSecret) || blank($refreshToken)) {
            return back()->with('error', 'Isi dulu Client ID, Client Secret, dan Refresh Token sebelum menguji koneksi.');
        }

        try {
            $disk = \Illuminate\Support\Facades\Storage::build([
                'driver' => 'google',
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
                'refreshToken' => $refreshToken,
                'folder' => Setting::get('backup_gdrive_folder', 'Lumora Backup'),
            ]);

            // Coba operasi ringan (buat & langsung hapus file kecil)
            // supaya benar-benar menguji tulis, bukan cuma baca.
            $testFile = '.lumora-test-' . now()->timestamp . '.txt';
            $disk->put($testFile, 'Uji koneksi dari Lumora Hosting — file ini aman dihapus.');
            $disk->delete($testFile);

            return back()->with('success', 'Berhasil terhubung ke Google Drive! Kredensial sudah benar.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal terhubung ke Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * Dijalankan langsung (bukan lewat antrian/queue) — sengaja, supaya
     * admin langsung tahu hasilnya (berhasil/gagal) saat itu juga,
     * bukan menunggu tanpa kepastian. Untuk database yang sangat besar
     * ini bisa memakan waktu; kalau nanti jadi masalah, baru dipindah
     * ke proses latar belakang.
     */
    public function runNow(): RedirectResponse
    {
        try {
            Artisan::call('lumora:backup');

            return back()->with('success', 'Backup berhasil dibuat. Lihat daftar di bawah.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Backup gagal: ' . $e->getMessage());
        }
    }

    public function download(string $filename)
    {
        $this->validateFilename($filename);

        $path = storage_path("app/backups/{$filename}");

        abort_unless(file_exists($path), 404);

        return response()->download($path);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $this->validateFilename($filename);

        $path = storage_path("app/backups/{$filename}");

        if (file_exists($path)) {
            unlink($path);
        }

        return back()->with('success', 'Cadangan berhasil dihapus.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'backup_retention' => ['required', 'integer', 'min:1', 'max:60'],
            'backup_enabled' => ['nullable', 'boolean'],
        ]);

        Setting::put('backup_retention', (string) $data['backup_retention'], 'general');
        Setting::put('backup_enabled', $request->boolean('backup_enabled') ? '1' : '0', 'general');

        return back()->with('success', 'Pengaturan backup disimpan.');
    }

    /**
     * Nama file backup dipakai LANGSUNG sebagai bagian path filesystem
     * di download()/destroy() — tanpa validasi ini, seseorang bisa
     * mengirim nama seperti "../../.env" dan mengunduh/menghapus file
     * di luar folder backups sama sekali (path traversal).
     */
    private function validateFilename(string $filename): void
    {
        abort_unless(
            preg_match('/^lumora-backup_[\d\-_]+\.zip$/', $filename) === 1,
            403,
            'Nama file tidak valid.'
        );
    }
}
