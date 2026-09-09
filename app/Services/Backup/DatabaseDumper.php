<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;

/**
 * Cadangan database ditulis murni pakai query PHP — SENGAJA tidak pakai
 * `mysqldump` lewat shell_exec()/exec(), karena banyak hosting berbagi
 * (termasuk kemungkinan besar server ini) mematikan fungsi shell demi
 * keamanan. Cara ini lebih lambat untuk database sangat besar, tapi
 * dijamin jalan di mana pun PHP+MySQL bisa jalan.
 */
class DatabaseDumper
{
    /**
     * Tulis dump SQL lengkap (struktur + isi semua tabel) ke file yang
     * diberikan. Ditulis baris-per-baris langsung ke file (bukan
     * dikumpulkan di memori dulu) supaya database besar tidak bikin PHP
     * kehabisan memory.
     */
    public function dumpTo(string $path): void
    {
        $handle = fopen($path, 'w');

        fwrite($handle, "-- Lumora Hosting — cadangan database\n");
        fwrite($handle, '-- Dibuat: ' . now()->toDateTimeString() . "\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0]);

        foreach ($tables as $table) {
            $this->dumpTable($handle, $table);
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
    }

    private function dumpTable($handle, string $table): void
    {
        // Struktur tabel
        $createStatement = DB::select("SHOW CREATE TABLE `{$table}`")[0];
        $createSql = $createStatement->{'Create Table'};

        fwrite($handle, "\n-- Struktur tabel `{$table}`\n");
        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($handle, $createSql . ";\n\n");

        // Isi tabel — diambil per potongan (chunk) supaya tabel besar
        // tidak sekaligus dimuat penuh ke memori.
        $count = DB::table($table)->count();

        if ($count === 0) {
            return;
        }

        fwrite($handle, "-- Isi tabel `{$table}` ({$count} baris)\n");

        DB::table($table)->orderBy(DB::raw('1'))->chunk(500, function ($rows) use ($handle, $table) {
            if ($rows->isEmpty()) {
                return;
            }

            $columns = array_keys((array) $rows->first());
            $columnList = implode('`, `', $columns);

            $valueLines = $rows->map(function ($row) {
                $values = array_map(function ($v) {
                    if (is_null($v)) {
                        return 'NULL';
                    }

                    return "'" . str_replace(
                        ["\\", "'", "\n", "\r", "\0"],
                        ["\\\\", "\\'", "\\n", "\\r", "\\0"],
                        (string) $v
                    ) . "'";
                }, (array) $row);

                return '(' . implode(', ', $values) . ')';
            })->implode(",\n");

            fwrite($handle, "INSERT INTO `{$table}` (`{$columnList}`) VALUES\n{$valueLines};\n");
        });

        fwrite($handle, "\n");
    }
}
