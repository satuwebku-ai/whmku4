<?php

namespace App\Console\Commands;

use App\Support\SchemaRepair;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan tabel `migrations` di database yang SUDAH berjalan dengan
 * nama file migrasi hasil penataan ulang (lihat database/migration-renames.php).
 *
 * Jalankan SEKALI, SEBELUM `php artisan migrate`, pada database lama.
 * Aman diulang (idempoten) dan tidak menyentuh tabel data apa pun kecuali
 * dengan opsi --drop-unused-tables (hanya tabel kosong yang tidak dipakai).
 *
 * Setelah riwayat diselaraskan, perintah ini juga melengkapi skema yang
 * ketinggalan (lihat App\Support\SchemaRepair): kolom/tabel yang dulu
 * ditambahkan migrasi terpisah lalu dilebur ke migrasi induknya tidak akan
 * pernah dibuat lagi oleh `migrate` di database yang induknya sudah tercatat
 * selesai -- mis. hasil restore backup lama. Otomatis ikut berjalan setelah
 * `lumora:restore`.
 */
class SyncMigrations extends Command
{
    protected $signature = 'lumora:sync-migrations
                            {--dry-run : Tampilkan perubahan tanpa menyimpannya}
                            {--no-repair : Lewati pelengkapan kolom/tabel yang hilang di backup lama}
                            {--drop-unused-tables : Hapus juga tabel kosong yang tidak dipakai lagi (hosting_packages, ticket_departments)}';

    protected $description = 'Sesuaikan riwayat migrasi database lama dengan nama file migrasi yang baru (jalankan sekali sebelum migrate)';

    /** Tabel yang dulu dibuat migrasi tapi tidak dipakai kode mana pun. */
    private const UNUSED_TABLES = ['hosting_packages', 'ticket_departments'];

    public function handle(): int
    {
        if (! Schema::hasTable('migrations')) {
            $this->info('Tabel migrations belum ada — ini instalasi baru, tidak ada yang perlu disinkronkan.');

            return self::SUCCESS;
        }

        $map = require database_path('migration-renames.php');
        $dry = (bool) $this->option('dry-run');
        $renamed = $removed = $skipped = 0;

        DB::transaction(function () use ($map, $dry, &$renamed, &$removed, &$skipped) {
            foreach ($map as $old => $new) {
                $oldRow = DB::table('migrations')->where('migration', $old)->first();

                if (! $oldRow) {
                    $skipped++;
                    continue;
                }

                if ($new === null || DB::table('migrations')->where('migration', $new)->exists()) {
                    $this->line("  hapus   {$old}");
                    $removed++;
                    if (! $dry) {
                        DB::table('migrations')->where('id', $oldRow->id)->delete();
                    }
                    continue;
                }

                $this->line("  ganti   {$old}\n       ->  {$new}");
                $renamed++;
                if (! $dry) {
                    DB::table('migrations')->where('id', $oldRow->id)->update(['migration' => $new]);
                }
            }
        });

        if ($this->option('drop-unused-tables')) {
            foreach (self::UNUSED_TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                if (DB::table($table)->exists()) {
                    $this->warn("  lewati tabel {$table}: masih berisi data.");
                    continue;
                }

                $this->line("  drop    tabel kosong {$table}");
                if (! $dry) {
                    Schema::drop($table);
                }
            }
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Selesai: {$renamed} diganti nama, {$removed} dibuang, {$skipped} tidak ada di database ini.");

        if (! $this->option('no-repair')) {
            $this->repairSchema($dry);
        }

        $this->line('Sekarang jalankan: php artisan migrate');

        return self::SUCCESS;
    }

    /** Lengkapi kolom/tabel yang hilang (database lama / hasil restore backup lama). */
    private function repairSchema(bool $dry): void
    {
        $this->line('');
        $this->line('Memeriksa kolom/tabel yang hilang (backup lama)...');

        $actions = SchemaRepair::run($dry);

        if ($actions === []) {
            $this->info('  skema sudah lengkap.');

            return;
        }

        foreach ($actions as $action) {
            $this->line(($dry ? '  perlu     ' : '  diperbaiki ') . $action);
        }

        $this->info(($dry ? '[dry-run] ' : '') . count($actions) . ' perbaikan skema' . ($dry ? ' diperlukan.' : ' dijalankan.'));
    }
}
