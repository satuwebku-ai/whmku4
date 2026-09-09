<?php

namespace App\Services\Domain;

use App\Models\Registrar;
use App\Services\Domain\Contracts\DomainRegistrarInterface;

/**
 * Placeholder integrasi ResellBiz / UK2Group.
 * API mereka (ResellerClub-compatible REST API) berbeda dari Namecheap,
 * jadi diimplementasikan terpisah — struktur interface sudah siap.
 */
class ResellBizService implements DomainRegistrarInterface
{
    public function __construct(protected Registrar $registrar) {}

    public function checkAvailability(array $domains): array
    {
        return ['success' => false, 'message' => $this->notImplementedMessage(), 'results' => [], 'raw' => null];
    }

    public function registerDomain(array $params): array
    {
        return $this->notImplemented();
    }

    public function renewDomain(string $domain, int $years): array
    {
        return $this->notImplemented();
    }

    public function setNameservers(string $domain, array $nameservers): array
    {
        return $this->notImplemented();
    }

    public function testConnection(): array
    {
        return $this->notImplemented();
    }

    private function notImplemented(): array
    {
        return ['success' => false, 'message' => $this->notImplementedMessage(), 'raw' => null];
    }

    private function notImplementedMessage(): string
    {
        return 'Integrasi ResellBiz/UK2Group belum tersedia di fase ini. Pilih registrar dengan provider Namecheap.';
    }
}
