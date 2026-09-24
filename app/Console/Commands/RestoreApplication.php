<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseRestorer;
use Illuminate\Console\Command;
use ZipArchive;

class RestoreApplication extends Command
{
    protected $signature = 'lumora:restore
        {file : Path lengkap ke file .zip cadangan}
        {--skip-files : Cuma pulihkan database, jangan timpa storage/app}
        {--force : Lewati konfirmasi interaktif (WAJIB untuk pemakaian non-interaktif/otomatis)}';

    protected $description = 'Pulihkan database + file upload dari satu file ZIP cadangan (kebalikan dari lumora:backup). MENIMPA seluruh data saat ini.';

    public function handle(DatabaseRestorer $restorer): int
    {
        $zipPath = $this->argument('file');

        if (! file_exists($zipPath)) {
            $this->error("File tidak ditemukan: {$zipPath}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'INI AKAN MENIMPA SELURUH DATA SAAT INI (semua tabel di-drop lalu dibuat ulang dari cadangan). Lanjutkan?',
            false
        )) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $tempDir = storage_path('app/backups/restore-tmp-' . now()->timestamp);
        mkdir($tempDir, 0755, true);

        try {
            $this->info('1/3 — Membongkar file ZIP...');

            $zip = new ZipArchive();

            if ($zip->open($zipPath) !== true) {
                $this->error('File ZIP tidak valid atau rusak.');

                return self::FAILURE;
            }

            $zip->extractTo($tempDir);
            $zip->close();

            $sqlPath = "{$tempDir}/database.sql";

            if (! file_exists($sqlPath)) {
                $this->error('File ZIP ini bukan cadangan Lumora yang valid (database.sql tidak ditemukan di dalamnya).');

                return self::FAILURE;
            }

            $this->info('2/3 — Memulihkan database (drop & buat ulang tiap tabel dari cadangan)...');

            $executed = $restorer->restoreFrom($sqlPath);

            $this->info("   {$executed} pernyataan SQL dijalankan.");

            if (! $this->option('skip-files')) {
                $storageAppBackup = "{$tempDir}/storage-app";

                if (is_dir($storageAppBackup)) {
                    $this->info('3/3 — Memulihkan file upload (storage/app)...');
                    $this->restoreStorageApp($storageAppBackup);
                } else {
                    $this->info('3/3 — Cadangan ini tidak berisi file upload, dilewati.');
                }
            } else {
                $this->info('3/3 — --skip-files dipakai, file upload tidak disentuh.');
            }

            $this->info('Selesai. Database' . ($this->option('skip-files') ? '' : ' + file upload') . ' berhasil dipulihkan.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Restore gagal di tengah proses: ' . $e->getMessage());
            $this->warn('Database mungkin dalam keadaan SEBAGIAN dipulihkan (MySQL tidak transaksional untuk DROP/CREATE TABLE) -- pulihkan lagi dari cadangan pra-restore yang dibuat otomatis sebelum ini, kalau ada.');

            return self::FAILURE;
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    /**
     * Menimpa storage/app dengan isi dari cadangan -- direktori
     * "backups" itu sendiri SENGAJA dikecualikan (persis seperti saat
     * dibuat di BackupApplication) supaya cadangan lain yang sudah ada
     * di server tidak ikut terhapus/tertimpa oleh proses restore.
     */
    private function restoreStorageApp(string $source): void
    {
        $destination = storage_path('app');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);

            if (str_starts_with($relativePath, 'backups')) {
                continue;
            }

            $targetPath = "{$destination}/{$relativePath}";

            if ($item->isDir()) {
                if (! is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $targetPath);
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
