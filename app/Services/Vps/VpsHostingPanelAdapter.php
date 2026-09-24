<?php

namespace App\Services\Vps;

use App\Services\Hosting\Contracts\HostingPanelInterface;
use App\Services\Vps\Contracts\VpsProviderInterface;

/**
 * Membungkus adapter VPS provider (VpsProviderInterface) supaya bisa dipakai
 * jalur provisioning/suspend/terminate yang memakai HostingPanelInterface
 * (HostingPanelFactory) -- tanpa menulis ulang jalur itu per provider.
 *
 * Padanan operasi:
 *   createAccount    -> create   (params['package'] = spesifikasi VM, JSON/array)
 *   suspendAccount   -> stop     ($username = ID VM di provider)
 *   unsuspendAccount -> start
 *   terminateAccount -> delete
 *   changePackage    -> resize   ($package = JSON spesifikasi baru)
 */
class VpsHostingPanelAdapter implements HostingPanelInterface
{
    public function __construct(private readonly VpsProviderInterface $provider) {}

    public function createAccount(array $params): array
    {
        return $this->provider->create($params);
    }

    public function suspendAccount(string $username, ?string $reason = null): array
    {
        return $this->provider->stop($username);
    }

    public function unsuspendAccount(string $username): array
    {
        return $this->provider->start($username);
    }

    public function terminateAccount(string $username): array
    {
        return $this->provider->delete($username);
    }

    public function changePackage(string $username, string $package): array
    {
        $spec = json_decode($package, true);

        if (! is_array($spec)) {
            return ['success' => false, 'message' => 'Spesifikasi paket VPS harus berupa JSON.', 'raw' => null];
        }

        return $this->provider->resize($username, $spec);
    }

    public function testConnection(): array
    {
        return $this->provider->testConnection();
    }
}
