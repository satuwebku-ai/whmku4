<?php

namespace App\Services\Hosting;

use App\Models\Server;
use App\Services\Hosting\Contracts\HostingPanelInterface;
use App\Services\Vps\VpsProviderFactory;
use InvalidArgumentException;

class HostingPanelFactory
{
    public static function make(Server $server): HostingPanelInterface
    {
        // Server VM/VPS (idcloudhost, digitalocean, dst) dipilih lewat
        // vps_provider -- bukan lewat jenis panel hosting.
        if ($server->isCloud()) {
            return VpsProviderFactory::hostingPanel($server);
        }

        return match ($server->panel) {
            'cpanel'      => new CpanelWhmService($server),
            'directadmin' => new DirectAdminService($server),
            'plesk'       => new PleskService($server),
            default       => throw new InvalidArgumentException("Panel [{$server->panel}] tidak dikenali."),
        };
    }
}
