<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseRestorer;
use App\Services\Backup\BackupSignature;
use Illuminate\Console\Command;
use ZipArchive;

class RestoreApplication extends Command
{
    protected $signature = 'lumora:restore
        {file : Path lengkap ke file .zip cadangan}
        {--skip-files : Cuma pulihkan database, jangan timpa storage/app}
        {--force : Lewati konfirmasi timpa data (WAJIB untuk pemakaian non-interaktif/otomatis)}
        {--allow-unsigned : Konfirmasi eksplisit untuk memulihkan backup lama tanpa tanda tangan}';

    protected $description = 'Pulihkan database + file upload dari satu file ZIP cadangan (kebalikan dari lumora:backup). MENIMPA seluruh data saat ini.';

    public function handle(DatabaseRestorer $restorer, BackupSignature $signature): int
    {
        $zipPath = $this->argument('file');

        if (! file_exists($zipPath)) {
            $this->error("File tidak ditemukan: {$zipPath}");

            return self::FAILURE;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->error('File ZIP tidak valid atau rusak.');

            return self::FAILURE;
        }

        $verification = $signature->inspectArchive($zip);
        if ($verification['status'] === 'invalid') {
            $zip->close();
            $this->error('Backup ditolak: ' . $verification['message']);

            return self::FAILURE;
        }

        if ($verification['status'] === 'unsigned') {
            $this->warn($verification['message'] . ' Keasliannya tidak dapat diverifikasi.');
            $unsignedConfirmed = $this->option('allow-unsigned')
                || (! $this->option('force') && $this->confirm(
                    'Pulihkan backup tanpa tanda tangan ini? Lanjutkan hanya jika sumber file dapat dipercaya.',
                    false
                ));

            if (! $unsignedConfirmed) {
                $zip->close();
                $this->error('Restore dibatalkan: konfirmasi --allow-unsigned diperlukan untuk backup lama.');

                return self::FAILURE;
            }
        } else {
            $this->info($verification['message']);
        }

        if (! $this->option('force') && ! $this->confirm(
            'INI AKAN MENIMPA SELURUH DATA SAAT INI (semua tabel di-drop lalu dibuat ulang dari cadangan). Lanjutkan?',
            false
        )) {
            $zip->close();
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $tempDir = storage_path('app/backups/restore-tmp-' . now()->timestamp . '-' . bin2hex(random_bytes(8)));
        if (! mkdir($tempDir, 0700, true) && ! is_dir($tempDir)) {
            $zip->close();
            $this->error('Folder sementara restore tidak dapat dibuat.');

            return self::FAILURE;
        }

        $zipOpen = true;
        try {
            $this->info('1/3 — Membongkar file ZIP...');

            $signature->extractSafely($zip, $tempDir);
            $zip->close();
            $zipOpen = false;

            $sqlPath = "{$tempDir}/database.sql";

            if (! file_exists($sqlPath)) {
                $this->error('File ZIP ini bukan cadangan Lumora yang valid (database.sql tidak ditemukan di dalamnya).');

                return self::FAILURE;
            }

            $this->info('2/3 — Memulihkan database (drop & buat ulang tiap tabel dari cadangan)...');

            $executed = $restorer->restoreFrom($sqlPath);

            $this->info("   {$executed} pernyataan SQL dijalankan.");

            // Tabel `migrations` ikut tertimpa dari cadangan. Cadangan yang dibuat
            // sebelum penataan ulang migrasi membawa nama file lama, jadi riwayatnya
            // diselaraskan dulu supaya `php artisan migrate` tidak mencoba membuat
            // ulang tabel yang sudah ada. Aman dijalankan berulang.
            try {
                $this->call('lumora:sync-migrations');
            } catch (\Throwable $e) {
                $this->warn('Penyelarasan riwayat migrasi gagal: ' . $e->getMessage());
                $this->warn('Jalankan manual: php artisan lumora:sync-migrations (sebelum php artisan migrate).');
            }

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
            if ($zipOpen) {
                $zip->close();
            }
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
