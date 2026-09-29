<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Pembaca .xlsx/.csv sederhana tanpa dependensi tambahan (xlsx hanyalah zip
 * berisi XML). Mengembalikan baris pertama sheet pertama sebagai array of array.
 */
class SpreadsheetReader
{
    public static function read(string $path, string $extension): array
    {
        return in_array(strtolower($extension), ['xlsx'], true) ? self::readXlsx($path) : self::readCsv($path);
    }

    private static function readCsv(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('File tidak bisa dibuka.');
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $firstLine = strtok($raw, "\n") ?: '';
        $delimiter = ',';
        foreach ([';', "\t", ','] as $candidate) {
            if (substr_count($firstLine, $candidate) > substr_count($firstLine, $delimiter)) {
                $delimiter = $candidate;
            }
        }

        $rows = [];
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $raw);
        rewind($stream);
        while (($line = fgetcsv($stream, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $line);
        }
        fclose($stream);

        return $rows;
    }

    private static function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip belum aktif di server. Simpan file sebagai CSV lalu impor ulang.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File bukan .xlsx yang valid.');
        }

        try {
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $ss = simplexml_load_string($xml);
                foreach ($ss->si ?? [] as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    } else {
                        foreach ($si->r ?? [] as $run) {
                            $text .= (string) $run->t;
                        }
                    }
                    $shared[] = $text;
                }
            }

            $sheetName = 'xl/worksheets/sheet1.xml';
            if ($zip->locateName($sheetName) === false) {
                $sheetName = null;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (preg_match('#^xl/worksheets/[^/]+\.xml$#', $name)) {
                        $sheetName = $name;
                        break;
                    }
                }
            }
            if (! $sheetName) {
                throw new RuntimeException('Sheet tidak ditemukan di file.');
            }

            $sheet = simplexml_load_string($zip->getFromName($sheetName));
            $rows = [];

            foreach ($sheet->sheetData->row ?? [] as $row) {
                $cells = [];
                foreach ($row->c as $c) {
                    $col = self::columnIndex((string) $c['r']);
                    $type = (string) $c['t'];
                    if ($type === 's') {
                        $value = $shared[(int) $c->v] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $value = (string) $c->is->t;
                    } else {
                        $value = (string) $c->v;
                        if ($value !== '' && is_numeric($value)) {
                            $value = $value + 0;
                        }
                    }
                    $cells[$col] = is_string($value) ? trim($value) : $value;
                }
                if ($cells === []) {
                    continue;
                }
                $max = max(array_keys($cells));
                $line = [];
                for ($i = 0; $i <= $max; $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $rows[] = $line;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private static function columnIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
        $letters = $m[1] ?? 'A';
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    /** "Rp 6.700.000,00" / "6700000" / 6700000.0 => 6700000.0 (null kalau bukan angka). */
    public static function parseMoney(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $s = preg_replace('/[^0-9.,-]/', '', (string) $value);
        if ($s === '' || $s === '-') {
            return null;
        }

        if (str_contains($s, ',') && str_contains($s, '.')) {
            // Format Indonesia (titik ribuan, koma desimal) atau sebaliknya: pemisah terakhir = desimal.
            if (strrpos($s, ',') > strrpos($s, '.')) {
                $s = str_replace(['.', ','], ['', '.'], $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif (str_contains($s, ',')) {
            $s = preg_match('/,\d{1,2}$/', $s) ? str_replace(',', '.', $s) : str_replace(',', '', $s);
        } elseif (substr_count($s, '.') > 1 || preg_match('/\.\d{3}$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
