<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Backup\SelectiveDatabaseRestorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ZipArchive;

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

    /**
     * Pulihkan dari salah satu cadangan yang SUDAH ADA di server (daftar
     * di halaman ini). Lihat performRestore() untuk pengaman yang
     * dijalankan sebelum data ditimpa.
     */
    public function restore(string $filename): RedirectResponse
    {
        $this->validateFilename($filename);

        $path = storage_path("app/backups/{$filename}");

        abort_unless(file_exists($path), 404);

        return $this->performRestore($path);
    }

    /**
     * Pulihkan dari file ZIP yang diunggah langsung (mis. hasil unduh
     * dari Google Drive, atau cadangan dari server lain) -- tidak harus
     * sudah ada di daftar backup server ini.
     */
    public function restoreUpload(Request $request): RedirectResponse
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:512000'], // maks 500MB
        ]);

        $uploadDir = storage_path('app/backups/uploads');

        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $tempPath = $uploadDir . '/' . uniqid('upload_') . '.zip';
        $request->file('backup_file')->move($uploadDir, basename($tempPath));

        try {
            return $this->performRestore($tempPath);
        } finally {
            // Salinan upload sementara ini SELALU dihapus setelah dipakai
            // (berhasil maupun gagal) -- bukan cadangan resmi yang perlu
            // disimpan, cuma titik singgah sebelum diproses.
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Inti proses restore, dipakai baik dari cadangan yang sudah ada
     * maupun dari upload baru.
     *
     * PENGAMAN WAJIB: cadangan keadaan SAAT INI dibuat dulu (ditandai
     * "pre-restore" di namanya) SEBELUM data ditimpa. Kalau langkah ini
     * sendiri gagal, seluruh proses restore DIBATALKAN -- tanpa jaring
     * pengaman ini, sekali restore salah pilih file berarti data
     * sebelumnya hilang permanen tanpa cara kembali.
     */
    private function performRestore(string $zipPath): RedirectResponse
    {
        if (! $this->makeSafetyBackup()) {
            return back()->with('error', 'Restore DIBATALKAN: gagal membuat cadangan pengaman dari data saat ini. Tidak ada data yang diubah.');
        }

        try {
            $exitCode = Artisan::call('lumora:restore', [
                'file' => $zipPath,
                '--force' => true,
            ]);

            $output = Artisan::output();

            if ($exitCode !== 0) {
                return back()->with('error', "Restore gagal: {$output}");
            }

            return back()->with('success', 'Database & file berhasil dipulihkan dari cadangan. Cadangan keadaan sebelumnya (pra-restore) sudah dibuat otomatis di daftar backup, kalau perlu kembali.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Restore gagal: ' . $e->getMessage());
        }
    }

    /**
     * Cadangan pengaman sebelum data ditimpa. `--force` WAJIB: tanpanya, kalau
     * "Backup otomatis" dimatikan di Pengaturan, lumora:backup langsung keluar
     * dengan status sukses TANPA membuat file apa pun — pengaman terlihat berhasil
     * padahal tidak ada cadangan sama sekali.
     */
    private function makeSafetyBackup(): bool
    {
        try {
            return Artisan::call('lumora:backup', ['--force' => true]) === 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ══════════════════════════════════════════════════════════════════
    //  Pemulihan SEBAGIAN — pilih tabel mana yang dimasukkan kembali
    // ══════════════════════════════════════════════════════════════════

    /** Halaman pilihan tabel untuk cadangan yang ada di daftar server. */
    public function selective(SelectiveDatabaseRestorer $restorer, string $filename): View|RedirectResponse
    {
        $this->validateFilename($filename);

        $path = storage_path("app/backups/{$filename}");

        abort_unless(file_exists($path), 404);

        return $this->showSelective(
            $restorer,
            $path,
            $filename,
            route('admin.backups.selective.run', $filename)
        );
    }

    public function selectiveRestore(Request $request, SelectiveDatabaseRestorer $restorer, string $filename): RedirectResponse
    {
        $this->validateFilename($filename);

        $path = storage_path("app/backups/{$filename}");

        abort_unless(file_exists($path), 404);

        return $this->runSelective($request, $restorer, $path, false);
    }

    /**
     * Langkah 1 untuk file unggahan: simpan sementara ZIP-nya supaya bisa dibuka lagi
     * di halaman pilihan & saat eksekusi (dua request terpisah).
     */
    public function selectiveUpload(Request $request): RedirectResponse
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:512000'], // maks 500MB
        ]);

        $dir = storage_path('app/backups/uploads');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pruneOldTemp($dir . '/staged_*.zip', 6 * 3600);

        $token = bin2hex(random_bytes(16));
        $request->file('backup_file')->move($dir, "staged_{$token}.zip");

        $path = "{$dir}/staged_{$token}.zip";
        $zip = new ZipArchive();

        if ($zip->open($path) !== true || $zip->locateName('database.sql') === false) {
            $zip->close();
            unlink($path);

            return back()->with('error', 'File ZIP ini bukan cadangan Lumora yang valid (database.sql tidak ditemukan di dalamnya).');
        }

        $zip->close();

        return redirect()->route('admin.backups.selective.staged', $token);
    }

    public function selectiveStaged(SelectiveDatabaseRestorer $restorer, string $token): View|RedirectResponse
    {
        $path = $this->stagedPath($token);

        if (! file_exists($path)) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'File unggahan sudah kedaluwarsa. Unggah ulang cadangannya.');
        }

        return $this->showSelective(
            $restorer,
            $path,
            'File unggahan',
            route('admin.backups.selective.staged.run', $token)
        );
    }

    public function selectiveRestoreStaged(Request $request, SelectiveDatabaseRestorer $restorer, string $token): RedirectResponse
    {
        $path = $this->stagedPath($token);

        if (! file_exists($path)) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'File unggahan sudah kedaluwarsa. Unggah ulang cadangannya.');
        }

        return $this->runSelective($request, $restorer, $path, true);
    }

    private function showSelective(SelectiveDatabaseRestorer $restorer, string $zipPath, string $label, string $action): View|RedirectResponse
    {
        @set_time_limit(0);

        $sqlPath = null;

        try {
            $sqlPath = $this->extractDatabaseSql($zipPath);

            return view('admin.backups.selective', [
                'sourceLabel' => $label,
                'action' => $action,
                'tables' => $restorer->inspect($sqlPath),
                'modes' => SelectiveDatabaseRestorer::modes(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'Gagal membaca cadangan: ' . $e->getMessage());
        } finally {
            if ($sqlPath && file_exists($sqlPath)) {
                unlink($sqlPath);
            }
        }
    }

    /**
     * Urutannya disengaja: (1) validasi pilihan → (2) cadangan pengaman → (3) restore.
     * Pilihan yang salah ditolak SEBELUM cadangan pengaman dibuat (yang bisa memakan
     * waktu), dan kalau cadangan pengaman gagal, tidak ada data yang disentuh.
     */
    private function runSelective(Request $request, SelectiveDatabaseRestorer $restorer, string $zipPath, bool $deleteZipWhenDone): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(SelectiveDatabaseRestorer::modes())],
            'tables' => ['required', 'array', 'min:1'],
            'tables.*' => ['string', 'max:128'],
        ], [
            'tables.required' => 'Pilih minimal satu tabel yang mau dipulihkan.',
            'tables.min' => 'Pilih minimal satu tabel yang mau dipulihkan.',
        ]);

        @set_time_limit(0);

        $sqlPath = null;
        $done = false;

        try {
            $sqlPath = $this->extractDatabaseSql($zipPath);

            $restorer->assertRestorable($sqlPath, $data['tables'], $data['mode']);

            if (! $this->makeSafetyBackup()) {
                return back()->withInput()->with('error', 'Restore DIBATALKAN: gagal membuat cadangan pengaman dari data saat ini. Tidak ada data yang diubah.');
            }

            $report = $restorer->restore($sqlPath, $data['tables'], $data['mode']);
            $done = true;

            return redirect()->route('admin.backups.index')
                ->with('success', $this->summarizeSelective($report, $data['mode']));
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            $hint = $data['mode'] === SelectiveDatabaseRestorer::MODE_REPLACE
                ? ' Tabel yang sedang diproses mungkin sebagian terpulihkan — cadangan pengaman (pra-restore) ada di daftar backup kalau perlu kembali.'
                : ' Semua perubahan dibatalkan (tidak ada data yang berubah).';

            return back()->withInput()->with('error', 'Restore gagal: ' . $e->getMessage() . '.' . $hint);
        } finally {
            if ($sqlPath && file_exists($sqlPath)) {
                unlink($sqlPath);
            }

            if ($done && $deleteZipWhenDone && file_exists($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    /** @param array<string, array<string, int>> $report */
    private function summarizeSelective(array $report, string $mode): string
    {
        $parts = [];

        foreach ($report as $table => $row) {
            $parts[] = match ($mode) {
                SelectiveDatabaseRestorer::MODE_REPLACE => "{$table}: {$row['backup_rows']} baris",
                SelectiveDatabaseRestorer::MODE_MISSING => "{$table}: {$row['inserted']} baris ditambahkan, " . ($row['backup_rows'] - $row['inserted']) . ' sudah ada',
                default => "{$table}: {$row['backup_rows']} baris diproses",
            };
        }

        $shown = implode('; ', array_slice($parts, 0, 6));

        if (count($parts) > 6) {
            $shown .= '; +' . (count($parts) - 6) . ' tabel lain';
        }

        return 'Pemulihan sebagian selesai (' . count($report) . " tabel) — {$shown}. Cadangan pengaman sebelum restore ada di daftar backup.";
    }

    /** Ambil HANYA database.sql dari ZIP (tanpa membongkar file upload yang bisa sangat besar). */
    private function extractDatabaseSql(string $zipPath): string
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('File ZIP tidak valid atau rusak.');
        }

        $in = $zip->getStream('database.sql');

        if ($in === false) {
            $zip->close();

            throw new \RuntimeException('Bukan cadangan Lumora yang valid (database.sql tidak ditemukan di dalamnya).');
        }

        $dir = storage_path('app/backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Sisa file sementara dari proses yang mati di tengah jalan (isinya data klien!).
        $this->pruneOldTemp($dir . '/sel-tmp_*.sql', 3600);

        $tmp = $dir . '/sel-tmp_' . bin2hex(random_bytes(8)) . '.sql';
        $out = fopen($tmp, 'w');

        stream_copy_to_stream($in, $out);

        fclose($in);
        fclose($out);
        $zip->close();

        return $tmp;
    }

    private function stagedPath(string $token): string
    {
        abort_unless(preg_match('/^[a-f0-9]{32}$/', $token) === 1, 404);

        return storage_path("app/backups/uploads/staged_{$token}.zip");
    }

    private function pruneOldTemp(string $pattern, int $maxAgeSeconds): void
    {
        foreach (glob($pattern) ?: [] as $file) {
            if (filemtime($file) < time() - $maxAgeSeconds) {
                @unlink($file);
            }
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
