<?php

namespace App\Services\Billing;

use App\Models\Product;
use App\Models\Server;

/**
 * Hitung biaya per jam dari spesifikasi VM + kartu harga yang berlaku.
 *
 * Kartu harga diprioritaskan dari PRODUK (Product::pricing_mode dkk) --
 * supaya beberapa produk VPS yang jalan di server yang sama boleh punya
 * harga jual sendiri-sendiri. Kalau produknya tidak (belum) punya kartu
 * harga sendiri (pricing_mode null -- termasuk VPS yang dibuat manual
 * lewat menu Layanan VPS tanpa produk sama sekali), jatuh ke kartu harga
 * SERVER seperti sebelumnya. `cost_cache` (harga modal dari provider)
 * selalu dari server, karena itu memang hasil sinkron API per-server,
 * bukan sesuatu yang masuk akal disimpan per-produk.
 *
 * Formula (persis seperti yang ditentukan):
 *   biaya/jam = (harga_CPU x jumlah_vCPU)
 *             + (harga_RAM x jumlah_GB_RAM)
 *             + (harga_storage_main x ukuran_disk_GB)
 *             + (harga_backup x ukuran_disk_GB, jika backup aktif)
 *             + (harga_snapshot x ukuran_snapshot_GB, jika ada snapshot)
 *             + (harga_windows_license x vCPU, jika OS Windows)
 */
class HourlyRateCalculator
{
    /**
     * @param  array{vcpu:int,ram:int,disk:int,os_name:string,backup_enabled:bool,snapshot_gb:float}  $spec
     *                RAM dalam MB (konsisten dengan format IdCloudHostService), disk & snapshot dalam GB.
     */
    public static function calculate(Server $server, array $spec, ?Product $product = null): float
    {
        // Provider berbasis size + mode markup: tarif = harga modal size
        // yang dipilih x markup (bukan hitungan per komponen).
        if (self::usesSizeMarkup($server, $product)) {
            return round(self::providerCost($server, $spec) * self::markupFactor(self::rateSource($server, $product)), 4);
        }

        return self::totalFromRates(self::effectiveRates($server, $product), $spec);
    }

    /**
     * Harga modal per jam (dalam Rupiah) untuk spek VM ini, langsung dari
     * data provider yang tersimpan di servers.cost_cache -- TANPA markup.
     * 0 kalau harga modal belum ditarik, size tidak dikenal, atau kurs
     * (untuk provider non-IDR) belum diisi -- artinya "belum bisa dihitung",
     * bukan "gratis".
     */
    public static function providerCost(Server $server, array $spec): float
    {
        $cache = $server->cost_cache;

        if (! is_array($cache) || $cache === []) {
            return 0.0;
        }

        if ($server->costModel() === 'size') {
            $size = $cache['sizes'][$spec['provider_size'] ?? ''] ?? null;

            return $size ? round((float) ($size['hourly'] ?? 0) * $server->costFxRate(), 4) : 0.0;
        }

        return self::totalFromRates(self::costRates($cache, $server->costFxRate()), $spec);
    }

    /** Total per jam dari tarif per komponen x spek VM. */
    private static function totalFromRates(array $rates, array $spec): float
    {
        $vcpu = (float) ($spec['vcpu'] ?? 0);
        $ramGb = (float) ($spec['ram'] ?? 0) / 1024; // disimpan dalam MB, formula butuh GB
        $diskGb = (float) ($spec['disk'] ?? 0);
        $snapshotGb = (float) ($spec['snapshot_gb'] ?? 0);
        $backupActive = (bool) ($spec['backup_enabled'] ?? false);
        $isWindows = str_contains(strtolower($spec['os_name'] ?? ''), 'windows');

        $total = 0.0;
        $total += $vcpu * $rates['vcpu'];
        $total += $ramGb * $rates['ram'];
        $total += $diskGb * $rates['storage'];

        if ($backupActive) {
            $total += $diskGb * $rates['backup'];
        }

        if ($snapshotGb > 0) {
            $total += $snapshotGb * $rates['snapshot'];
        }

        if ($isWindows) {
            $total += $vcpu * $rates['windows'];
        }

        return round($total, 4);
    }

    /** Sumber kartu harga: produk kalau punya kartu harga sendiri, kalau tidak server. */
    private static function rateSource(Server $server, ?Product $product): Server|Product
    {
        return ($product && $product->hasHourlyRateCard()) ? $product : $server;
    }

    private static function usesSizeMarkup(Server $server, ?Product $product): bool
    {
        return self::rateSource($server, $product)->pricing_mode === 'markup' && $server->costModel() === 'size';
    }

    private static function markupFactor(Server|Product $source): float
    {
        return 1 + ((float) ($source->markup_percent ?? 0) / 100);
    }

    /**
     * Tarif jual per komponen yang benar-benar dipakai.
     *
     * Mode "manual": pakai angka yang diketik admin di kartu harga.
     * Mode "markup": hitung dari harga modal provider (di-cache dari
     * /pricing/policy) + persentase markup -- jadi kalau provider naik
     * harga, harga jual ikut naik otomatis tanpa perlu diedit, dan
     * tidak ada risiko diam-diam jual di bawah modal.
     *
     * Sumber kartu harga: PRODUK dulu (kalau pricing_mode-nya sudah
     * diisi admin di halaman Produk), baru jatuh ke SERVER kalau produk
     * tidak dikirim atau belum punya kartu harga sendiri.
     */
    public static function effectiveRates(Server $server, ?Product $product = null): array
    {
        return self::ratesFromSource(self::rateSource($server, $product), $server);
    }

    /** Tarif per komponen dari harga modal (cost_cache) x pengali (markup / kurs). */
    private static function costRates(array $costCache, float $factor = 1.0): array
    {
        return [
            'vcpu'     => (float) ($costCache['vcpu'] ?? 0) * $factor,
            'ram'      => (float) ($costCache['ram'] ?? 0) * $factor,
            'storage'  => (float) ($costCache['storage'] ?? 0) * $factor,
            'backup'   => (float) ($costCache['backup'] ?? 0) * $factor,
            'snapshot' => (float) ($costCache['snapshot'] ?? 0) * $factor,
            'windows'  => (float) ($costCache['windows'] ?? 0) * $factor,
        ];
    }

    private static function ratesFromSource(Server|Product $source, Server $server): array
    {
        $costCache = $server->cost_cache;

        if ($source->pricing_mode === 'markup' && is_array($costCache)) {
            return self::costRates($costCache, self::markupFactor($source) * $server->costFxRate());
        }

        return [
            'vcpu'     => (float) ($source->price_per_vcpu_hour ?? 0),
            'ram'      => (float) ($source->price_per_ram_gb_hour ?? 0),
            'storage'  => (float) ($source->price_per_storage_gb_hour ?? 0),
            'backup'   => (float) ($source->price_per_backup_gb_hour ?? 0),
            'snapshot' => (float) ($source->price_per_snapshot_gb_hour ?? 0),
            'windows'  => (float) ($source->price_windows_license_per_vcpu_hour ?? 0),
        ];
    }

    /**
     * Rincian per komponen -- dipakai untuk ditampilkan ke admin/klien
     * (mis. di halaman kelola VM nanti), supaya jelas dari mana angka
     * totalnya berasal, bukan cuma satu angka tanpa penjelasan.
     *
     * Memakai effectiveRates() yang sama dengan calculate() (bukan baca
     * price_per_*_hour langsung) -- supaya rincian di sini selalu cocok
     * dengan total yang ditagihkan, bukan dua angka berbeda yang
     * membingungkan.
     */
    public static function breakdown(Server $server, array $spec, ?Product $product = null): array
    {
        if (self::usesSizeMarkup($server, $product)) {
            return ['VPS (' . ($spec['provider_size'] ?? '-') . ')' => self::calculate($server, $spec, $product)];
        }

        $rates = self::effectiveRates($server, $product);

        $vcpu = (float) ($spec['vcpu'] ?? 0);
        $ramGb = (float) ($spec['ram'] ?? 0) / 1024;
        $diskGb = (float) ($spec['disk'] ?? 0);
        $snapshotGb = (float) ($spec['snapshot_gb'] ?? 0);
        $backupActive = (bool) ($spec['backup_enabled'] ?? false);
        $isWindows = str_contains(strtolower($spec['os_name'] ?? ''), 'windows');

        $lines = [
            'CPU' => $vcpu * $rates['vcpu'],
            'RAM' => $ramGb * $rates['ram'],
            'Storage' => $diskGb * $rates['storage'],
        ];

        if ($backupActive) {
            $lines['Backup'] = $diskGb * $rates['backup'];
        }

        if ($snapshotGb > 0) {
            $lines['Snapshot'] = $snapshotGb * $rates['snapshot'];
        }

        if ($isWindows) {
            $lines['Lisensi Windows'] = $vcpu * $rates['windows'];
        }

        return $lines;
    }

    /**
     * Tarif efektif untuk SATU hosting account -- true kalau server
     * account itu ada kartu harganya & speknya diketahui, jatuh ke
     * hourly_rate manual kalau tidak. Logic ini sebelumnya ditulis
     * ulang identik di 3 tempat berbeda (Admin\VpsController::rateFor(),
     * Client\VpsController::rateFor(), ChargeHourlyUsage::effectiveRate())
     * -- disatukan di sini supaya kalau aturannya berubah, cukup diubah
     * sekali. Otomatis pakai kartu harga produk kalau account ini
     * terhubung ke produk yang sudah diatur (lihat effectiveRates()).
     */
    public static function forAccount(\App\Models\HostingAccount $account): ?float
    {
        if ($account->serverModel && $account->hasVmSpec()) {
            $rate = self::calculate($account->serverModel, $account->vmSpec(), $account->product);

            if ($rate > 0) {
                return $rate;
            }
        }

        return $account->hourly_rate ? (float) $account->hourly_rate : null;
    }
}

