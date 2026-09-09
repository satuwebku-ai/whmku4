<?php

namespace App\Services\Billing;

use App\Models\Server;

/**
 * Hitung biaya per jam dari spesifikasi VM + kartu harga SERVER
 * tempat VM itu berjalan (bukan kartu harga global) -- supaya tiap
 * provider cloud (IDCloudHost sekarang, provider lain nanti) bisa
 * punya tarif sendiri-sendiri tanpa saling memengaruhi.
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
    public static function calculate(Server $server, array $spec): float
    {
        $rates = self::effectiveRates($server);

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

    /**
     * Tarif jual per komponen yang benar-benar dipakai.
     *
     * Mode "manual": pakai angka yang diketik admin di kartu harga.
     * Mode "markup": hitung dari harga modal provider (di-cache dari
     * /pricing/policy) + persentase markup -- jadi kalau provider naik
     * harga, harga jual ikut naik otomatis tanpa perlu diedit, dan
     * tidak ada risiko diam-diam jual di bawah modal.
     */
    public static function effectiveRates(Server $server): array
    {
        if ($server->pricing_mode === 'markup' && is_array($server->cost_cache)) {
            $factor = 1 + ((float) ($server->markup_percent ?? 0) / 100);
            $cost = $server->cost_cache;

            return [
                'vcpu'     => (float) ($cost['vcpu'] ?? 0) * $factor,
                'ram'      => (float) ($cost['ram'] ?? 0) * $factor,
                'storage'  => (float) ($cost['storage'] ?? 0) * $factor,
                'backup'   => (float) ($cost['backup'] ?? 0) * $factor,
                'snapshot' => (float) ($cost['snapshot'] ?? 0) * $factor,
                'windows'  => (float) ($cost['windows'] ?? 0) * $factor,
            ];
        }

        return [
            'vcpu'     => (float) ($server->price_per_vcpu_hour ?? 0),
            'ram'      => (float) ($server->price_per_ram_gb_hour ?? 0),
            'storage'  => (float) ($server->price_per_storage_gb_hour ?? 0),
            'backup'   => (float) ($server->price_per_backup_gb_hour ?? 0),
            'snapshot' => (float) ($server->price_per_snapshot_gb_hour ?? 0),
            'windows'  => (float) ($server->price_windows_license_per_vcpu_hour ?? 0),
        ];
    }

    /**
     * Rincian per komponen -- dipakai untuk ditampilkan ke admin/klien
     * (mis. di halaman kelola VM nanti), supaya jelas dari mana angka
     * totalnya berasal, bukan cuma satu angka tanpa penjelasan.
     */
    public static function breakdown(Server $server, array $spec): array
    {
        $vcpu = (float) ($spec['vcpu'] ?? 0);
        $ramGb = (float) ($spec['ram'] ?? 0) / 1024;
        $diskGb = (float) ($spec['disk'] ?? 0);
        $snapshotGb = (float) ($spec['snapshot_gb'] ?? 0);
        $backupActive = (bool) ($spec['backup_enabled'] ?? false);
        $isWindows = str_contains(strtolower($spec['os_name'] ?? ''), 'windows');

        $lines = [
            'CPU' => $vcpu * (float) ($server->price_per_vcpu_hour ?? 0),
            'RAM' => $ramGb * (float) ($server->price_per_ram_gb_hour ?? 0),
            'Storage' => $diskGb * (float) ($server->price_per_storage_gb_hour ?? 0),
        ];

        if ($backupActive) {
            $lines['Backup'] = $diskGb * (float) ($server->price_per_backup_gb_hour ?? 0);
        }

        if ($snapshotGb > 0) {
            $lines['Snapshot'] = $snapshotGb * (float) ($server->price_per_snapshot_gb_hour ?? 0);
        }

        if ($isWindows) {
            $lines['Lisensi Windows'] = $vcpu * (float) ($server->price_windows_license_per_vcpu_hour ?? 0);
        }

        return $lines;
    }
}
