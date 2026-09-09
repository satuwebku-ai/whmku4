<?php

namespace App\Console\Commands;

use App\Models\Registrar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Menampilkan respons MENTAH dari endpoint harga Liqu.id.
 *
 * Dipakai saat harga modal tetap 0 setelah sinkronisasi: kita perlu tahu
 * bentuk asli JSON-nya supaya parser bisa dicocokkan. Struktur respons
 * Liqu.id tidak didokumentasikan publik, jadi harus dilihat langsung.
 */
class LiquidPriceDebug extends Command
{
    protected $signature = 'lumora:liquid-prices
                            {--registrar= : ID registrar (default: registrar Liqu.id pertama yang aktif)}
                            {--timeout=120 : Batas waktu per request dalam detik}
                            {--chars=3000 : Berapa karakter respons yang ditampilkan}';

    protected $description = 'Diagnostik: tampilkan respons mentah endpoint harga Liqu.id';

    public function handle(): int
    {
        $registrar = $this->option('registrar')
            ? Registrar::find($this->option('registrar'))
            : Registrar::where('provider', 'liquid')->where('is_active', true)->first();

        if (! $registrar) {
            $this->error('Registrar Liqu.id tidak ditemukan. Pastikan sudah ditambahkan dan aktif.');

            return self::FAILURE;
        }

        $base = $registrar->api_base_url;
        $timeout = (int) $this->option('timeout');
        $chars = (int) $this->option('chars');

        $this->info("Registrar : {$registrar->name} (#{$registrar->id})");
        $this->info("Base URL  : {$base}");
        $this->info('Reseller  : ' . $registrar->api_username);
        $this->info('Mode      : ' . ($registrar->sandbox ? 'sandbox' : 'produksi'));
        $this->newLine();

        $endpoints = [
            '/account/prices',
            '/resellers/prices',
            '/customers/prices',
            // Detail satu TLD — kadang harga ikut di sini.
            '/tlds/com',
        ];

        foreach ($endpoints as $endpoint) {
            $this->line(str_repeat('─', 70));
            $this->line("GET {$endpoint}");
            $this->line(str_repeat('─', 70));

            $started = microtime(true);

            try {
                $response = Http::baseUrl($base)
                    ->withBasicAuth((string) $registrar->api_username, (string) $registrar->api_key)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->get($endpoint);

                $elapsed = round(microtime(true) - $started, 1);
                $body = $response->body();

                $this->line("HTTP {$response->status()} · {$elapsed} detik · " . strlen($body) . ' byte');

                if (blank($body)) {
                    $this->warn('Respons KOSONG.');
                    $this->newLine();
                    continue;
                }

                $json = $response->json();

                if (is_array($json)) {
                    $this->line('Jumlah entri: ' . count($json));

                    // Tampilkan satu entri contoh — inilah yang saya butuhkan
                    // untuk memetakan nama field harga.
                    $firstKey = array_key_first($json);
                    $this->newLine();
                    $this->line("Contoh entri (key: " . var_export($firstKey, true) . "):");
                    $this->line(json_encode(
                        $json[$firstKey],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    ));
                }

                $this->newLine();
                $this->line('Potongan respons mentah:');
                $this->line(substr($body, 0, $chars));

                if (strlen($body) > $chars) {
                    $this->comment('… (dipotong, pakai --chars untuk melihat lebih banyak)');
                }
            } catch (\Throwable $e) {
                $elapsed = round(microtime(true) - $started, 1);
                $this->error("GAGAL setelah {$elapsed} detik: " . $e->getMessage());
            }

            $this->newLine();
        }

        $this->line(str_repeat('═', 70));
        $this->comment('Kirim keluaran di atas supaya parser harga bisa disesuaikan.');
        $this->comment('Kredensial tidak ikut tercetak, jadi aman dibagikan.');

        return self::SUCCESS;
    }
}
