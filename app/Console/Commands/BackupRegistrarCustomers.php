<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Registrar;
use App\Services\Domain\DomainRegistrarFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Cadangkan data customer & domain dari API registrar ke file CSV.
 *
 * KENAPA INI ADA: kalau database lokal rusak/hilang, data yang benar-benar
 * "milik" kita ada di dua tempat -- database ini DAN akun reseller di
 * registrar. Yang di registrar tidak ikut hilang, jadi bisa dipakai
 * memulihkan siapa saja klien kita dan domain apa saja yang mereka punya.
 *
 * PENTING soal keterbatasannya (jangan sampai dikira cadangan penuh):
 *
 *  - Ini BUKAN pengganti backup database. Data yang cuma ada di sistem ini
 *    (invoice, tiket, akun hosting, saldo, harga jual) TIDAK ikut tersimpan
 *    di registrar, jadi tidak bisa ditarik balik dari sini. Tetap jalankan
 *    backup database secara terpisah.
 *
 *  - Cara mengambil datanya beda per registrar:
 *      * Liqu.id  -> punya endpoint "daftar semua customer", jadi seluruh
 *                    customer bisa ditarik walau tidak tercatat di sini.
 *      * DNAMA    -> TIDAK punya endpoint daftar customer; cuma bisa cari
 *                    per username. Jadi daftar username-nya diambil dari
 *                    tabel domains di database ini dulu, baru detailnya
 *                    ditanyakan satu per satu ke DNAMA. Artinya kalau
 *                    tabel domains ikut hilang, jalur DNAMA tidak punya
 *                    titik awal -- karena itu file CSV ini sebaiknya
 *                    dijadwalkan rutin SEBELUM terjadi apa-apa.
 *
 * Contoh pakai:
 *   php artisan registrar:backup-customers
 *   php artisan registrar:backup-customers --registrar=2
 */
class BackupRegistrarCustomers extends Command
{
    protected $signature = 'registrar:backup-customers
                            {--registrar= : ID registrar tertentu (default: semua yang aktif)}
                            {--path=backups/registrar-customers : Folder tujuan di disk local}';

    protected $description = 'Cadangkan data customer & domain dari API registrar ke CSV';

    public function handle(): int
    {
        $registrars = Registrar::when($this->option('registrar'), fn ($q) => $q->where('id', $this->option('registrar')))
            ->when(! $this->option('registrar'), fn ($q) => $q->where('is_active', true))
            ->get();

        if ($registrars->isEmpty()) {
            $this->error('Tidak ada registrar yang cocok.');

            return self::FAILURE;
        }

        $folder = trim((string) $this->option('path'), '/');
        $stamp = now()->format('Y-m-d_His');
        $totalRows = 0;

        foreach ($registrars as $registrar) {
            $this->info("Mengambil dari: {$registrar->name} ({$registrar->provider})");

            try {
                $rows = $this->collect($registrar);
            } catch (Throwable $e) {
                $this->error("  Gagal: {$e->getMessage()}");
                continue;
            }

            if (empty($rows)) {
                $this->warn('  Tidak ada data yang bisa diambil.');
                continue;
            }

            $path = "{$folder}/{$stamp}_{$registrar->provider}_{$registrar->id}.csv";
            Storage::disk('local')->put($path, $this->toCsv($rows));

            $this->line('  Tersimpan: ' . count($rows) . " baris -> storage/app/{$path}");
            $totalRows += count($rows);
        }

        if ($totalRows === 0) {
            $this->warn('Selesai, tapi tidak ada satu pun baris tersimpan.');

            return self::FAILURE;
        }

        $this->info("Selesai. Total {$totalRows} baris tersimpan.");
        $this->line('Unduh file-nya lewat cPanel File Manager di storage/app/' . $folder);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collect(Registrar $registrar): array
    {
        $service = DomainRegistrarFactory::make($registrar);

        // Jalur 1: registrar yang punya endpoint daftar customer.
        if (method_exists($service, 'listCustomers')) {
            // Limit dinaikkan jauh di atas default (20) -- ini cadangan,
            // jadi maunya selengkap mungkin, bukan sekadar halaman pertama.
            $result = $service->listCustomers(500);

            if ($result['success']) {
                return array_map(fn ($c) => [
                    'registrar' => $registrar->name,
                    'sumber' => 'API (daftar customer)',
                    'customer_id' => $c['id'] ?? '',
                    'username' => $c['username'] ?? ($c['email'] ?? ''),
                    'nama' => $c['name'] ?? '',
                    'email' => $c['email'] ?? '',
                    'perusahaan' => $c['company'] ?? '',
                    'domain' => '',
                ], $result['customers']);
            }

            $this->warn('  Daftar customer gagal diambil: ' . $result['message']);
        }

        // Jalur 2: registrar tanpa endpoint daftar (mis. DNAMA) -- daftar
        // username diambil dari domain yang tercatat di sistem ini, lalu
        // detailnya ditanyakan satu per satu.
        $domains = Domain::with('client')
            ->where('registrar_id', $registrar->id)
            ->get();

        if ($domains->isEmpty()) {
            return [];
        }

        $rows = [];
        $cache = [];

        $bar = $this->output->createProgressBar($domains->count());
        $bar->start();

        foreach ($domains as $domain) {
            $username = $domain->client->email ?? null;
            $detail = null;

            if ($username && method_exists($service, 'getCustomer')) {
                if (! array_key_exists($username, $cache)) {
                    try {
                        $res = $service->getCustomer($username);
                        $cache[$username] = $res['success'] ? ($res['raw']['data'] ?? null) : null;
                    } catch (Throwable) {
                        $cache[$username] = null;
                    }
                }

                $detail = $cache[$username];
            }

            $rows[] = [
                'registrar' => $registrar->name,
                'sumber' => $detail ? 'API (cari per username)' : 'Database lokal',
                'customer_id' => '',
                'username' => $username ?? '',
                'nama' => $detail['name'] ?? ($domain->client->name ?? ''),
                'email' => $detail['email'] ?? ($domain->client->email ?? ''),
                'perusahaan' => $detail['company_name'] ?? '',
                'domain' => $domain->domain_name,
            ];

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $rows;
    }

    private function toCsv(array $rows): string
    {
        $out = fopen('php://temp', 'r+');

        fputcsv($out, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($out, $row);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
