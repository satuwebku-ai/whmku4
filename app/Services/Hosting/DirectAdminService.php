<?php

namespace App\Services\Hosting;

use App\Models\Server;
use App\Services\Hosting\Contracts\HostingPanelInterface;

/**
 * Placeholder integrasi DirectAdmin.
 * Struktur sudah disiapkan (implements HostingPanelInterface) supaya
 * gampang diisi nanti — endpoint DirectAdmin API berbeda dari WHM
 * (pakai CMD_API_* bukan JSON API), jadi diimplementasikan terpisah.
 */
class DirectAdminService implements HostingPanelInterface
{
    public function __construct(protected Server $server) {}

    public function createAccount(array $params): array
    {
        return $this->notImplemented();
    }

    public function suspendAccount(string $username, ?string $reason = null): array
    {
        return $this->notImplemented();
    }

    public function unsuspendAccount(string $username): array
    {
        return $this->notImplemented();
    }

    public function terminateAccount(string $username): array
    {
        return $this->notImplemented();
    }

    public function changePackage(string $username, string $package): array
    {
        return $this->notImplemented();
    }

    public function testConnection(): array
    {
        return $this->notImplemented();
    }

    private function notImplemented(): array
    {
        return [
            'success' => false,
            'message' => 'Integrasi DirectAdmin belum tersedia di fase ini. Pilih server dengan panel cPanel/WHM.',
            'raw' => null,
        ];
    }
}
