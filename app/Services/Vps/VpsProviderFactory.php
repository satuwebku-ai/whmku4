<?php

namespace App\Services\Vps;

use App\Models\Server;
use App\Services\Hosting\Contracts\HostingPanelInterface;
use App\Services\Hosting\IdCloudHostService;
use App\Services\Vps\Contracts\VpsProviderInterface;
use InvalidArgumentException;

class VpsProviderFactory
{
    /**
     * Adapter API VPS (VpsProviderInterface) untuk server bertipe VM/VPS.
     * Provider baru: tambah entri di config/vps_providers.php + satu baris
     * di match() ini.
     */
    public static function make(Server $server): VpsProviderInterface
    {
        $driver = $server->vpsDriver();

        return match ($driver) {
            'idcloudhost' => new IdCloudHostVpsProvider(new IdCloudHostService($server)),
            'digitalocean' => new DigitalOceanVpsProvider($server),
            default => throw new InvalidArgumentException('VPS provider [' . ($driver ?? '-') . '] belum memiliki adapter aktif. Tambahkan adapter tanpa mengubah controller.'),
        };
    }

    /**
     * Versi HostingPanelInterface untuk jalur provisioning/suspend/terminate
     * (dipanggil HostingPanelFactory). IDCloudHost punya service sendiri yang
     * sudah mengimplementasikan interface itu; provider lain dibungkus adapter.
     */
    public static function hostingPanel(Server $server): HostingPanelInterface
    {
        if ($server->vpsDriver() === 'idcloudhost') {
            return new IdCloudHostService($server);
        }

        return new VpsHostingPanelAdapter(self::make($server));
    }

    /** Daftar provider untuk dropdown: kunci => nama (dari config/vps_providers.php). */
    public static function supported(): array
    {
        return collect(config('vps_providers', []))
            ->map(fn (array $cfg, string $key) => $cfg['label'] ?? ucfirst($key))
            ->all();
    }
}
