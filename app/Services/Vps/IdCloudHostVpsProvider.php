<?php

namespace App\Services\Vps;

use App\Models\Server;
use App\Services\Vps\Contracts\VpsProviderInterface;
use App\Services\Hosting\IdCloudHostService;

class IdCloudHostVpsProvider implements VpsProviderInterface
{
    public function __construct(private readonly IdCloudHostService $service) {}

    public function create(array $params): array { return $this->service->createAccount($params); }
    public function get(string $id): array { return $this->service->getVmInfo($id); }
    public function start(string $id): array { return $this->service->unsuspendAccount($id); }
    public function stop(string $id, bool $force = false): array { return $force ? $this->service->forceStop($id) : $this->service->suspendAccount($id); }
    public function restart(string $id): array {
        $stop = $this->service->suspendAccount($id);
        if (! $stop['success']) return $stop;
        usleep(500000);
        return $this->service->unsuspendAccount($id);
    }
    public function delete(string $id): array { return $this->service->terminateAccount($id); }
    public function changePassword(string $id, string $username, string $password): array { return $this->service->changePassword($id, $username, $password); }
    public function reinstall(string $id, ?string $osName = null, ?string $osVersion = null): array { return $this->service->reinstall($id, $osName, $osVersion); }
    public function resize(string $id, array $spec): array { return $this->service->changePackage($id, json_encode($spec)); }
    public function testConnection(): array { return $this->service->testConnection(); }
    public function images(): array { return $this->service->listVmImages(); }
    public function locations(): array { return $this->service->listLocations(); }
    public function pools(): array { return $this->service->listHostPools(); }
    public function parameters(): array { return $this->service->getVmParameters(); }

    /**
     * Harga modal dari /pricing/policy. Tiap komponen punya beberapa
     * tingkatan (tier); yang disimpan adalah tingkat TERENDAH (paling umum
     * dipakai). Lihat catatan tiering di ServerController::idCloudHostDiagnosticsData().
     */
    public function pricing(): array
    {
        $result = $this->service->getPricingPolicy();

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'], 'cost' => null];
        }

        $tiers = ['cpu' => [], 'ram' => [], 'main' => [], 'backup' => [], 'snapshot' => []];
        $windows = 0;

        foreach (($result['raw']['policy'] ?? []) as $policy) {
            $price = (float) ($policy['pricePerUnit'] ?? $policy['price'] ?? 0);
            $type = $policy['resourceType'] ?? '';
            $service = $policy['serviceNameInUptime'] ?? '';

            if ($type === 'CPU') {
                $tiers['cpu'][(int) ($policy['numCpus'] ?? 0)] = $price;
            } elseif ($type === 'RAM') {
                $tiers['ram'][(int) ($policy['megsRam'] ?? 0)] = $price;
            } elseif ($type === 'STORAGE' && isset($tiers[$service])) {
                $tiers[$service][(int) ($policy['gigsStorage'] ?? 0)] = $price;
            } elseif ($type === 'LICENSE' && $service === 'windows') {
                $windows = $price;
            }
        }

        $lowest = function (array $list) {
            if (! $list) return 0;
            ksort($list);

            return reset($list);
        };

        return [
            'success' => true,
            'message' => 'OK',
            'cost'    => [
                'model'    => 'component',
                'currency' => 'IDR',
                'vcpu'     => $lowest($tiers['cpu']),
                'ram'      => $lowest($tiers['ram']),
                'storage'  => $lowest($tiers['main']),
                'backup'   => $lowest($tiers['backup']),
                'snapshot' => $lowest($tiers['snapshot']),
                'windows'  => $windows,
            ],
        ];
    }
}
