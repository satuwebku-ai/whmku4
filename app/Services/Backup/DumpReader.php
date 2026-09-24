<?php

namespace App\Services\Backup;

use Generator;
use RuntimeException;

/**
 * Pembaca file dump (database.sql hasil DatabaseDumper) secara STREAMING —
 * dibaca baris per baris, tidak dimuat utuh ke memori. Dipakai oleh pemulihan
 * sebagian (SelectiveDatabaseRestorer), yang harus bisa mengenali tiap
 * pernyataan itu milik tabel yang mana.
 *
 * Aturan pemisahan pernyataan SAMA dengan DatabaseRestorer: titik koma di
 * dalam string berkutip (mis. "Alamat: Jl. A; RT 01") TIDAK dianggap akhir
 * pernyataan, dan escape `\'` / `\\` (skema DatabaseDumper) dihormati.
 */
class DumpReader
{
    /**
     * @return Generator<int, string> tiap pernyataan SQL utuh (berakhir ';')
     */
    public function statements(string $path): Generator
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Tidak bisa membaca file dump: {$path}");
        }

        $buffer = '';
        $hasContent = false; // sudah ada isi SQL sungguhan (bukan cuma komentar/kosong)?
        $inString = false;

        try {
            while (($line = fgets($handle)) !== false) {
                // Di luar string & belum ada isi: baris kosong / komentar utuh dibuang.
                if (! $inString && ! $hasContent) {
                    $trimmed = ltrim($line);

                    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                        continue;
                    }
                }

                $hasContent = true;
                $len = strlen($line);
                $pos = 0;
                $start = 0; // bagian baris ini yang belum dipindah ke $buffer

                while ($pos < $len) {
                    if ($inString) {
                        $pos += strcspn($line, "\\'", $pos);

                        if ($pos >= $len) {
                            break;
                        }

                        if ($line[$pos] === '\\') {
                            $pos += 2; // karakter setelah backslash selalu bagian dari string

                            continue;
                        }

                        $inString = false; // kutip penutup
                        $pos++;

                        continue;
                    }

                    $pos += strcspn($line, "';", $pos);

                    if ($pos >= $len) {
                        break;
                    }

                    if ($line[$pos] === "'") {
                        $inString = true;
                        $pos++;

                        continue;
                    }

                    // ';' di luar string = akhir pernyataan
                    $buffer .= substr($line, $start, $pos + 1 - $start);
                    yield $buffer;

                    $buffer = '';
                    $hasContent = false;
                    $pos++;
                    $start = $pos;

                    // Sisa baris setelah ';' yang cuma spasi/komentar dibuang.
                    $rest = ltrim(substr($line, $pos));

                    if ($rest === '' || str_starts_with($rest, '--')) {
                        $start = $len;
                        break;
                    }

                    $hasContent = true;
                }

                if ($start < $len) {
                    $buffer .= substr($line, $start);
                }
            }
        } finally {
            fclose($handle);
        }

        if (trim($buffer) !== '') {
            yield $buffer;
        }
    }

    /**
     * Kenali pernyataan milik tabel mana. Pernyataan lain (SET FOREIGN_KEY_CHECKS, dsb.) → null.
     *
     * @return array{type: string, table: string}|null type: drop|create|insert
     */
    public function classify(string $statement): ?array
    {
        $head = substr($statement, 0, 300);

        if (! preg_match('/^\s*(DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE|INSERT\s+INTO)\s+`([^`]+)`/i', $head, $m)) {
            return null;
        }

        $keyword = strtoupper($m[1]);

        $type = str_starts_with($keyword, 'DROP') ? 'drop'
            : (str_starts_with($keyword, 'CREATE') ? 'create' : 'insert');

        return ['type' => $type, 'table' => $m[2]];
    }

    /**
     * Ringkasan isi dump: per tabel → pernyataan CREATE, daftar kolom, jumlah baris.
     *
     * @return array<string, array{create: ?string, columns: array<int, string>, rows: int}>
     */
    public function scan(string $path): array
    {
        $tables = [];

        foreach ($this->statements($path) as $statement) {
            $info = $this->classify($statement);

            if ($info === null) {
                continue;
            }

            $table = $info['table'];
            $tables[$table] ??= ['create' => null, 'columns' => [], 'rows' => 0];

            if ($info['type'] === 'create') {
                $tables[$table]['create'] = $statement;
                $tables[$table]['columns'] = $this->columnsFromCreate($statement);
            } elseif ($info['type'] === 'insert') {
                $tables[$table]['rows'] += $this->rowCount($statement);
            }
        }

        return $tables;
    }

    /**
     * Baris kolom di CREATE TABLE selalu diawali backtick; baris KEY / PRIMARY KEY /
     * CONSTRAINT diawali kata kunci, jadi tidak ikut tertangkap.
     *
     * @return array<int, string>
     */
    public function columnsFromCreate(string $createStatement): array
    {
        preg_match_all('/^\s+`([^`]+)`\s/m', $createStatement, $m);

        return $m[1];
    }

    /**
     * Daftar kolom dari bagian "INSERT INTO `t` (`a`, `b`) VALUES".
     *
     * @return array<int, string>
     */
    public function columnsFromInsert(string $insertStatement): array
    {
        $end = strpos($insertStatement, ') VALUES');

        if ($end === false) {
            return [];
        }

        $start = strpos($insertStatement, '(');
        $list = substr($insertStatement, $start + 1, $end - $start - 1);

        return array_map(fn ($c) => trim($c, '` '), explode('`, `', $list));
    }

    /**
     * DatabaseDumper menulis satu baris per baris data, dan newline di dalam nilai
     * di-escape jadi "\n" — jadi ",\n(" murni pemisah antar baris.
     */
    public function rowCount(string $insertStatement): int
    {
        return substr_count($insertStatement, "),\n(") + 1;
    }
}
