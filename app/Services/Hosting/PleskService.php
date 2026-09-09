<?php

namespace App\Services\Hosting;

use App\Models\Server;
use App\Services\Hosting\Contracts\HostingPanelInterface;

/**
 * Placeholder integrasi Plesk (REST API / XML-RPC).
 * Sama seperti DirectAdminService — struktur siap, implementasi menyusul.
 */
class PleskService implements HostingPanelInterface
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
            'message' => 'Integrasi Plesk belum tersedia di fase ini. Pilih server dengan panel cPanel/WHM.',
            'raw' => null,
        ];
    }
}
