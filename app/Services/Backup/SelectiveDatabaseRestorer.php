<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Pemulihan SEBAGIAN: cuma tabel yang dipilih yang dipulihkan dari database.sql
 * cadangan; tabel lain tidak disentuh sama sekali. (Pemulihan penuh tetap lewat
 * DatabaseRestorer / lumora:restore.)
 *
 * Tiga mode:
 *  - replace : tabel di-DROP lalu dibuat ulang dari cadangan (struktur + isi).
 *              Isi & struktur tabel saat ini diganti persis seperti cadangan.
 *  - missing : cuma MENAMBAH baris yang belum ada (kunci utama/unik belum dipakai);
 *              baris yang sudah ada di database sekarang tidak diubah. Cocok untuk
 *              mengembalikan data yang terhapus tanpa menimpa perubahan terbaru.
 *  - upsert  : baris yang belum ada ditambah, baris dengan kunci sama DITIMPA isi
 *              cadangan; baris yang cuma ada sekarang (data baru) tetap dibiarkan.
 *
 * Mode missing/upsert dijalankan dalam SATU transaksi (semua-atau-tidak-sama-sekali).
 * Mode replace tidak bisa transaksional (DROP/CREATE TABLE di MySQL otomatis commit).
 */
class SelectiveDatabaseRestorer
{
    public const MODE_REPLACE = 'replace';
    public const MODE_MISSING = 'missing';
    public const MODE_UPSERT = 'upsert';

    public function __construct(private DumpReader $reader)
    {
    }

    /** @return array<int, string> */
    public static function modes(): array
    {
        return [self::MODE_REPLACE, self::MODE_MISSING, self::MODE_UPSERT];
    }

    /**
     * Daftar tabel di dalam cadangan + perbandingannya dengan database sekarang,
     * untuk halaman pilihan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function inspect(string $sqlPath): array
    {
        $rows = [];

        foreach ($this->reader->scan($sqlPath) as $table => $info) {
            $exists = Schema::hasTable($table);
            $blockers = $this->mergeBlockers($table, $info, $exists);

            $structureDiffers = false;
            $added = [];   // kolom ada di tabel sekarang, belum ada di cadangan
            $removed = []; // kolom ada di cadangan, sudah tidak ada di tabel sekarang
            $required = []; // sebagian $added yang NOT NULL tanpa default (INSERT dari cadangan akan gagal)

            if ($exists && $info['columns'] !== []) {
                $currentCols = Schema::getColumns($table);
                $current = array_map(fn ($c) => strtolower($c['name']), $currentCols);
                $backup = array_map('strtolower', $info['columns']);

                $removedKeys = array_diff($backup, $current);
                $addedKeys = array_diff($current, $backup);

                $removed = array_values(array_filter($info['columns'], fn ($c) => in_array(strtolower($c), $removedKeys, true)));

                foreach ($currentCols as $col) {
                    if (! in_array(strtolower($col['name']), $addedKeys, true)) {
                        continue;
                    }

                    $added[] = $col['name'];

                    if (! $col['nullable'] && $col['default'] === null && ! $col['auto_increment']) {
                        $required[] = $col['name'];
                    }
                }

                $structureDiffers = $added !== [] || $removed !== [];
            }

            $rows[] = [
                'table' => $table,
                'backup_rows' => $info['rows'],
                'current_rows' => $exists ? DB::table($table)->count() : null,
                'exists' => $exists,
                'structure_differs' => $structureDiffers,
                'columns_added' => $added,
                'columns_removed' => $removed,
                'columns_required' => $required,
                'merge_blockers' => $blockers, // kosong = boleh dipakai mode missing/upsert
            ];
        }

        return $rows;
    }

    /**
     * Pastikan pilihan valid SEBELUM ada yang dijalankan (dipanggil sebelum cadangan
     * pengaman dibuat, supaya pilihan yang salah tidak memicu backup sia-sia).
     *
     * @param  array<int, string>  $tables
     *
     * @throws InvalidArgumentException
     */
    public function assertRestorable(string $sqlPath, array $tables, string $mode): void
    {
        $this->validate($this->reader->scan($sqlPath), $tables, $mode);
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<string, array<string, int>> ringkasan per tabel
     *
     * @throws InvalidArgumentException pilihan tidak valid (belum ada yang diubah)
     */
    public function restore(string $sqlPath, array $tables, string $mode): array
    {
        $tables = array_values(array_unique($tables));

        $this->validate($this->reader->scan($sqlPath), $tables, $mode);

        DB::unprepared('SET FOREIGN_KEY_CHECKS=0');

        try {
            if ($mode === self::MODE_REPLACE) {
                return $this->apply($sqlPath, $tables, $mode);
            }

            DB::beginTransaction();

            try {
                $report = $this->apply($sqlPath, $tables, $mode);
                DB::commit();

                return $report;
            } catch (\Throwable $e) {
                DB::rollBack();

                throw $e;
            }
        } finally {
            DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<string, array<string, int>>
     */
    private function apply(string $sqlPath, array $tables, string $mode): array
    {
        $selected = array_flip($tables);
        $pdo = DB::connection()->getPdo();

        $report = [];

        foreach ($tables as $table) {
            $report[$table] = ['backup_rows' => 0, 'inserted' => 0];
        }

        foreach ($this->reader->statements($sqlPath) as $statement) {
            $info = $this->reader->classify($statement);

            if ($info === null || ! isset($selected[$info['table']])) {
                continue;
            }

            $table = $info['table'];

            if ($mode === self::MODE_REPLACE) {
                $affected = $pdo->exec($statement);

                if ($info['type'] === 'insert') {
                    $count = $this->reader->rowCount($statement);
                    $report[$table]['backup_rows'] += $count;
                    $report[$table]['inserted'] += $count;
                }

                continue;
            }

            // Mode gabung: struktur tabel TIDAK disentuh, cuma INSERT yang dipakai.
            if ($info['type'] !== 'insert') {
                continue;
            }

            $report[$table]['backup_rows'] += $this->reader->rowCount($statement);

            $affected = $pdo->exec($this->rewriteInsert($statement, $mode));

            if ($mode === self::MODE_MISSING) {
                $report[$table]['inserted'] += (int) $affected;
            } else {
                // upsert: angka "affected" MySQL tidak bisa dipakai menghitung
                // baris baru vs tertimpa, jadi yang dilaporkan = baris yang diproses.
                $report[$table]['inserted'] = $report[$table]['backup_rows'];
            }
        }

        return $report;
    }

    private function rewriteInsert(string $statement, string $mode): string
    {
        $statement = rtrim(ltrim($statement), "; \t\r\n");
        $columns = $this->reader->columnsFromInsert($statement);

        if ($mode === self::MODE_MISSING) {
            // Bentrok kunci → baris dilewati lewat update "tanpa perubahan" (kolom = dirinya
            // sendiri). SENGAJA bukan INSERT IGNORE: IGNORE ikut menelan error lain (kolom
            // NOT NULL tanpa default, data terpotong, dst.) jadi masalah nyata malah lolos
            // diam-diam dan transaksi tidak pernah dibatalkan.
            $first = $columns[0];

            return "{$statement} ON DUPLICATE KEY UPDATE `{$first}` = `{$first}`;";
        }

        $updates = implode(', ', array_map(
            fn ($c) => "`{$c}` = VALUES(`{$c}`)",
            $columns
        ));

        return "{$statement} ON DUPLICATE KEY UPDATE {$updates};";
    }

    /**
     * @param  array<string, array{create: ?string, columns: array<int, string>, rows: int}>  $scan
     * @param  array<int, string>  $tables
     */
    private function validate(array $scan, array $tables, string $mode): void
    {
        if (! in_array($mode, self::modes(), true)) {
            throw new InvalidArgumentException('Mode pemulihan tidak dikenal.');
        }

        if ($tables === []) {
            throw new InvalidArgumentException('Pilih minimal satu tabel yang mau dipulihkan.');
        }

        foreach ($tables as $table) {
            if (! isset($scan[$table])) {
                throw new InvalidArgumentException("Tabel `{$table}` tidak ada di dalam cadangan ini.");
            }

            if ($mode === self::MODE_REPLACE) {
                if ($scan[$table]['create'] === null) {
                    throw new InvalidArgumentException("Cadangan tidak memuat struktur tabel `{$table}`.");
                }

                continue;
            }

            $blockers = $this->mergeBlockers($table, $scan[$table], Schema::hasTable($table));

            if ($blockers !== []) {
                throw new InvalidArgumentException(
                    "Tabel `{$table}` tidak bisa dipulihkan dengan mode ini: " . implode('; ', $blockers) . '. Pakai mode "Timpa tabel" untuk tabel ini.'
                );
            }
        }
    }

    /**
     * Alasan sebuah tabel TIDAK aman untuk mode gabung (missing/upsert).
     *
     * @param  array{create: ?string, columns: array<int, string>, rows: int}  $info
     * @return array<int, string>
     */
    private function mergeBlockers(string $table, array $info, bool $exists): array
    {
        if (! $exists) {
            return ['tabel ini tidak ada di database sekarang'];
        }

        $blockers = [];

        $current = array_map('strtolower', Schema::getColumnListing($table));
        $gone = array_values(array_filter(
            $info['columns'],
            fn ($c) => ! in_array(strtolower($c), $current, true)
        ));

        if ($gone !== []) {
            $blockers[] = 'kolom ' . implode(', ', $gone) . ' di cadangan sudah tidak ada di tabel sekarang';
        }

        // Tanpa kunci unik, INSERT tidak bisa tahu baris mana yang sudah ada →
        // tiap pemulihan akan menggandakan seluruh baris.
        if (! $this->hasUniqueKey($table)) {
            $blockers[] = 'tabel tidak punya kunci utama/unik (baris akan tergandakan)';
        }

        return $blockers;
    }

    private function hasUniqueKey(string $table): bool
    {
        return DB::select("SHOW INDEX FROM `{$table}` WHERE Non_unique = 0") !== [];
    }
}
