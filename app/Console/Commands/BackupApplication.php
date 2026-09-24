<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Backup\DatabaseDumper;
use Illuminate\Console\Command;
use ZipArchive;

class BackupApplication extends Command
{
    protected $signature = 'lumora:backup
        {--keep= : Berapa cadangan terakhir yang disimpan (kosongkan untuk pakai pengaturan admin)}
        {--force : Tetap buat cadangan walau "Backup otomatis" dimatikan di Pengaturan (dipakai sebagai pengaman sebelum restore)}';

    protected $description = 'Backup database + file yang diupload (bukti bayar, dokumen domain, logo) jadi satu file ZIP.';

    
    public function handle(DatabaseDumper $dumper): int
    {
        ob_start();
        $result = $this->handleJob($dumper);
        $output = ob_get_clean();
        echo $output;

        \App\Models\CronJob::recordExecution('lumora:backup', $result === self::SUCCESS, $output);

        return $result;
    }

    private function handleJob(DatabaseDumper $dumper): int
    {
        // Sebelumnya kondisi "backup_enabled" cuma dicek di jadwal lama
        // (routes/console.php) -- begitu semua tugas dipindah ke sistem
        // lumora:cron, toggle ini jadi diam-diam terabaikan (backup
        // tetap jalan walau admin mematikannya). Dicek langsung di sini
        // supaya berlaku apa pun cara command ini dipicu.
        if (! $this->option('force') && Setting::get('backup_enabled', '1') !== '1') {
            $this->info('Backup otomatis sedang dimatikan di Pengaturan — dilewati.');

            return self::SUCCESS;
        }

        $keep = (int) ($this->option('keep') ?: Setting::get('backup_retention', 7));

        $dir = storage_path('app/backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $sqlPath = "{$dir}/db_{$timestamp}.sql";
        $zipPath = "{$dir}/lumora-backup_{$timestamp}.zip";

        $this->info('1/3 — Membuat dump database (murni PHP, tanpa mysqldump)...');

        try {
            $dumper->dumpTo($sqlPath);
        } catch (\Throwable $e) {
            $this->error('Gagal membuat dump database: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('2/3 — Mengemas database + file upload jadi satu ZIP...');

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->error('Gagal membuat file ZIP.');

            return self::FAILURE;
        }

        $zip->addFile($sqlPath, 'database.sql');

        // Seluruh storage/app -- mencakup file publik (logo, bukti
        // transfer) DAN file privat (dokumen domain, dsb) dalam satu
        // cadangan, supaya tidak ada yang tercecer.
        $storageAppPath = storage_path('app');
        $this->addDirectoryToZip($zip, $storageAppPath, 'storage-app', ['backups']);

        $zip->close();
        unlink($sqlPath); // .sql mentah sudah ikut masuk ZIP, tidak perlu disimpan dobel

        $sizeMb = round(filesize($zipPath) / 1024 / 1024, 2);
        $this->info("3/3 — Selesai. Ukuran: {$sizeMb} MB. Disimpan: {$zipPath}");

        $this->uploadToGoogleDrive($zipPath, basename($zipPath));

        $this->cleanupOldBackups($dir, $keep);

        return self::SUCCESS;
    }

    /**
     * Opsional — cuma jalan kalau admin sudah isi kredensial Google
     * Drive di Admin -> Backup. Kegagalan di sini TIDAK menggagalkan
     * seluruh proses backup (file lokalnya sudah aman tersimpan duluan),
     * cuma dicatat sebagai peringatan.
     */
    private function uploadToGoogleDrive(string $zipPath, string $filename): void
    {
        if (Setting::get('backup_gdrive_enabled') !== '1') {
            return;
        }

        $clientId = Setting::get('backup_gdrive_client_id');
        $clientSecret = Setting::get('backup_gdrive_client_secret');
        $refreshToken = Setting::get('backup_gdrive_refresh_token');

        if (blank($clientId) || blank($clientSecret) || blank($refreshToken)) {
            $this->warn('Google Drive aktif tapi kredensialnya belum lengkap diisi — dilewati.');

            return;
        }

        $this->info('Mengunggah ke Google Drive...');

        try {
            $disk = \Illuminate\Support\Facades\Storage::build([
                'driver' => 'google',
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
                'refreshToken' => $refreshToken,
                'folder' => Setting::get('backup_gdrive_folder', 'Lumora Backup'),
            ]);

            $disk->put($filename, fopen($zipPath, 'r'));

            $this->info('Berhasil diunggah ke Google Drive.');
        } catch (\Throwable $e) {
            $this->warn('Gagal unggah ke Google Drive (backup lokal tetap aman): ' . $e->getMessage());
        }
    }

    private function addDirectoryToZip(ZipArchive $zip, string $sourcePath, string $zipRoot, array $excludeDirs = []): void
    {
        if (! is_dir($sourcePath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $relativePath = substr($file->getPathname(), strlen($sourcePath) + 1);

            // Lewati folder yang sengaja dikecualikan (mis. "backups"
            // sendiri, supaya cadangan tidak ikut mencadangkan dirinya
            // sendiri berulang-ulang setiap kali dijalankan).
            $topLevelDir = explode(DIRECTORY_SEPARATOR, $relativePath)[0];

            if (in_array($topLevelDir, $excludeDirs, true)) {
                continue;
            }

            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), $zipRoot . '/' . str_replace('\\', '/', $relativePath));
            }
        }
    }

    /**
     * Cadangan lama dihapus otomatis — tanpa ini, tiap backup harian
     * yang menumpuk lama-lama bisa menghabiskan kuota penyimpanan
     * hosting tanpa disadari.
     */
    private function cleanupOldBackups(string $dir, int $keep): void
    {
        $files = collect(glob("{$dir}/lumora-backup_*.zip"))
            ->sortByDesc(fn ($f) => filemtime($f))
            ->values();

        $toDelete = $files->slice(max($keep, 1));

        foreach ($toDelete as $file) {
            unlink($file);
        }

        if ($toDelete->isNotEmpty()) {
            $this->info("Menghapus {$toDelete->count()} cadangan lama (menyisakan {$keep} terbaru).");
        }
    }
}
