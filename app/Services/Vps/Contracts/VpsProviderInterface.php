<?php

namespace App\Services\Vps\Contracts;

use App\Models\Server;

interface VpsProviderInterface
{
    public function create(array $params): array;
    public function get(string $id): array;
    public function start(string $id): array;
    public function stop(string $id, bool $force = false): array;
    public function restart(string $id): array;
    public function delete(string $id): array;
    public function changePassword(string $id, string $username, string $password): array;
    public function reinstall(string $id, ?string $osName = null, ?string $osVersion = null): array;
    public function resize(string $id, array $spec): array;
    public function testConnection(): array;
    public function images(): array;
    public function locations(): array;
    public function pools(): array;
    public function parameters(): array;

    /**
     * Tarik harga modal dari provider, dinormalkan supaya bisa disimpan di
     * servers.cost_cache dan dipakai HourlyRateCalculator.
     *
     * @return array{success: bool, message: string, cost: ?array}
     *         cost['model'] = 'component' (vcpu/ram/storage/backup/snapshot/windows)
     *                       | 'size' (sizes[slug] => hourly/monthly/vcpu/ram/disk)
     *         cost['currency'] = mata uang angka-angka itu (IDR, USD, ...)
     */
    public function pricing(): array;
}
