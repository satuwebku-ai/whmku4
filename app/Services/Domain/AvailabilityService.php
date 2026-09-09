<?php

namespace App\Services\Domain;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cek ketersediaan domain lewat RDAP — protokol publik pengganti WHOIS.
 *
 * Kenapa tidak memakai API registrar (Liqu.id):
 *
 *  - Registrar punya rate limit ketat (±100 request / 15 menit). Pencarian
 *    domain adalah fitur yang dipakai pengunjung terus-menerus, jadi kuota
 *    itu cepat habis dan mudah disalahgunakan orang lain.
 *  - Endpoint availability Liqu.id sering timeout dan menolak seluruh
 *    batch kalau ada satu ekstensi yang tidak didukung.
 *  - Cek ketersediaan tidak butuh kredensial apa pun — RDAP terbuka untuk
 *    umum dan dijalankan langsung oleh registry tiap TLD.
 *
 * Registrasi domain TETAP lewat registrar, karena itu memang butuh akun
 * reseller. Yang dipindah ke sini hanya proses pengecekannya.
 *
 * Cara baca hasil RDAP:
 *   HTTP 404 → domain tidak terdaftar  = TERSEDIA
 *   HTTP 200 → domain terdaftar        = TIDAK TERSEDIA
 *   lainnya  → tidak bisa dipastikan   = null (jangan ditebak)
 */
class AvailabilityService
{
    /**
     * rdap.org adalah layanan bootstrap: ia meneruskan permintaan ke server
     * RDAP resmi milik registry TLD yang bersangkutan, jadi kita tidak perlu
     * memelihara daftar server per-TLD sendiri.
     */
    private const RDAP_BASE = 'https://rdap.org/domain/';

    /** Hasil di-cache supaya pencarian berulang tidak memukul RDAP terus. */
    private const CACHE_MINUTES = 10;

    /** Batas waktu per domain — dibuat pendek karena dijalankan paralel. */
    private const TIMEOUT = 8;

    /**
     * Cek banyak domain sekaligus secara paralel.
     *
     * @param  string[]  $domains
     * @return array{success: bool, message: string, results: array<string, ?bool>, unknown: string[]}
     */
    public function check(array $domains): array
    {
        $domains = array_values(array_unique(array_filter($domains)));

        if (empty($domains)) {
            return ['success' => true, 'message' => 'OK', 'results' => [], 'unknown' => []];
        }

        $results = [];
        $needFetch = [];

        // Ambil dulu yang masih ada di cache.
        foreach ($domains as $domain) {
            $cached = Cache::get($this->cacheKey($domain));

            if ($cached !== null) {
                $results[$domain] = $cached === 'available';
                continue;
            }

            $needFetch[] = $domain;
        }

        if ($needFetch) {
            $results += $this->fetchMany($needFetch);
        }

        // Domain yang statusnya tidak bisa dipastikan dipisahkan, supaya
        // halaman bisa menyampaikannya apa adanya alih-alih menebak.
        $unknown = array_keys(array_filter($results, fn ($v) => $v === null));

        // Urutkan mengikuti urutan permintaan agar tampilannya konsisten.
        $ordered = [];

        foreach ($domains as $domain) {
            if (array_key_exists($domain, $results)) {
                $ordered[$domain] = $results[$domain];
            }
        }

        return [
            'success' => true,
            'message' => 'OK',
            'results' => $ordered,
            'unknown' => $unknown,
        ];
    }

    /**
     * Kirim permintaan RDAP secara paralel.
     *
     * @param  string[]  $domains
     * @return array<string, ?bool>
     */
    private function fetchMany(array $domains): array
    {
        $results = [];

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($domain) => $pool->as($domain)
                    ->timeout(self::TIMEOUT)
                    ->withHeaders(['Accept' => 'application/rdap+json'])
                    ->get(self::RDAP_BASE . urlencode($domain)),
                $domains
            ));
        } catch (Throwable $e) {
            Log::warning('RDAP: gagal menjalankan permintaan paralel: ' . $e->getMessage());

            return array_fill_keys($domains, null);
        }

        foreach ($domains as $domain) {
            $results[$domain] = $this->interpret($domain, $responses[$domain] ?? null);
        }

        return $results;
    }

    /**
     * Terjemahkan respons RDAP jadi status ketersediaan.
     */
    private function interpret(string $domain, mixed $response): ?bool
    {
        // Item dalam pool bisa berupa exception, bukan response.
        if ($response instanceof Throwable) {
            Log::info("RDAP {$domain}: " . $response->getMessage());

            return null;
        }

        if (! $response || ! method_exists($response, 'status')) {
            return null;
        }

        $status = $response->status();

        $available = match (true) {
            $status === 404 => true,   // tidak terdaftar
            $status === 200 => false,  // terdaftar
            default => null,           // 429/5xx/TLD tanpa RDAP
        };

        if ($available !== null) {
            Cache::put(
                $this->cacheKey($domain),
                $available ? 'available' : 'taken',
                now()->addMinutes(self::CACHE_MINUTES)
            );
        }

        return $available;
    }

    private function cacheKey(string $domain): string
    {
        return 'rdap:' . strtolower($domain);
    }
}
