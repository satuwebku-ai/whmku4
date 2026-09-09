<?php

namespace App\Services\Hosting;

use App\Models\Server;
use App\Services\Hosting\Contracts\HostingPanelInterface;
use InvalidArgumentException;

class HostingPanelFactory
{
    public static function make(Server $server): HostingPanelInterface
    {
        return match ($server->panel) {
            'cpanel'      => new CpanelWhmService($server),
            'directadmin' => new DirectAdminService($server),
            'plesk'       => new PleskService($server),
            'idcloudhost' => new IdCloudHostService($server),
            default       => throw new InvalidArgumentException("Panel [{$server->panel}] tidak dikenali."),
        };
    }
}
