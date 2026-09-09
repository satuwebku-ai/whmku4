<?php

namespace App\Services\Hosting\Contracts;

interface HostingPanelInterface
{
    /**
     * Membuat akun hosting baru di panel.
     *
     * @param  array{domain: string, username: string, password: string, package: string, email: string}  $params
     * @return array{success: bool, message: string, raw: mixed}
     */
    public function createAccount(array $params): array;

    /**
     * @return array{success: bool, message: string, raw: mixed}
     */
    public function suspendAccount(string $username, ?string $reason = null): array;

    /**
     * @return array{success: bool, message: string, raw: mixed}
     */
    public function unsuspendAccount(string $username): array;

    /**
     * @return array{success: bool, message: string, raw: mixed}
     */
    public function terminateAccount(string $username): array;

    /**
     * @return array{success: bool, message: string, raw: mixed}
     */
    public function changePackage(string $username, string $package): array;

    /**
     * Uji koneksi & kredensial ke server panel.
     *
     * @return array{success: bool, message: string, raw: mixed}
     */
    public function testConnection(): array;
}
