<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;

/**
 * Kebalikan dari DatabaseDumper -- membaca file .sql hasil dump-nya lalu
 * menjalankan tiap pernyataan satu per satu. SENGAJA tidak memakai
 * `mysql < file.sql` lewat shell_exec() (alasan sama seperti
 * DatabaseDumper: banyak hosting berbagi mematikan fungsi shell).
 *
 * Pemisahan pernyataan TIDAK BOLEH cuma explode(';', $sql) -- nilai
 * kolom di dump ini bisa saja mengandung karakter titik koma di dalam
 * string (mis. catatan "Alamat: Jl. A; RT 01"), dan itu TIDAK dianggap
 * akhir pernyataan oleh MySQL sungguhan. splitStatements() di bawah
 * melacak apakah posisi baca sedang di dalam string berkutip satu
 * (dengan escape `\'` dan `\\` -- persis skema escape yang dipakai
 * DatabaseDumper::dumpTable()) supaya titik koma di dalam string tidak
 * ikut memecah pernyataan.
 */
class DatabaseRestorer
{
    /**
     * @return int jumlah pernyataan yang berhasil dijalankan
     */
    public function restoreFrom(string $sqlPath): int
    {
        $sql = file_get_contents($sqlPath);

        if ($sql === false) {
            throw new \RuntimeException("Tidak bisa membaca file dump: {$sqlPath}");
        }

        $sql = $this->stripCommentLines($sql);

        $executed = 0;

        foreach ($this->splitStatements($sql) as $statement) {
            $statement = trim($statement);

            if ($statement === '') {
                continue;
            }

            DB::unprepared($statement);
            $executed++;
        }

        return $executed;
    }

    /**
     * Buang baris yang SELURUHNYA komentar ("-- ...") SEBELUM dipecah
     * jadi pernyataan -- bukan sesudahnya.
     *
     * BUG SEBELUMNYA: DatabaseDumper menulis komentar di baris sendiri
     * TANPA titik koma penutup, langsung diikuti pernyataan sungguhan
     * di baris berikutnya (mis. "-- Struktur tabel `x`\nDROP TABLE...;").
     * Karena baris komentar tidak diakhiri ';', splitStatements()
     * menggabungkan komentar itu DENGAN pernyataan sungguhan yang
     * mengikutinya jadi SATU potongan. Kalau pemeriksaan "apakah
     * komentar" baru dilakukan SESUDAH dipecah (cuma cek awal potongan),
     * seluruh potongan gabungan itu -- termasuk perintah DROP TABLE /
     * SET FOREIGN_KEY_CHECKS sungguhan di baris berikutnya -- ikut
     * terbuang dianggap "cuma komentar". Akibatnya DROP TABLE untuk
     * SEMUA tabel tidak pernah benar-benar jalan, dan CREATE TABLE
     * setelahnya gagal "already exists" kalau tabelnya sudah ada
     * sebelumnya (mis. dari migrate). Menghapus baris komentar DI SINI,
     * sebelum pemecahan, memastikan tiap potongan yang tersisa cuma
     * berisi SQL sungguhan.
     */
    private function stripCommentLines(string $sql): string
    {
        $lines = explode("\n", $sql);

        $lines = array_filter($lines, function (string $line) {
            return ! str_starts_with(ltrim($line), '--');
        });

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $buffer .= $char;

            if ($inString) {
                if ($char === '\\') {
                    // Karakter setelah backslash SELALU bagian dari
                    // string (escaped), termasuk kalau kebetulan tanda
                    // kutip -- lompat langsung tanpa dievaluasi ulang,
                    // sama seperti aturan escape di DatabaseDumper.
                    if ($i + 1 < $length) {
                        $buffer .= $sql[$i + 1];
                        $i++;
                    }

                    continue;
                }

                if ($char === "'") {
                    $inString = false;
                }

                continue;
            }

            if ($char === "'") {
                $inString = true;

                continue;
            }

            if ($char === ';') {
                $statements[] = $buffer;
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }
}
