<?php

namespace App\Services\Domain;

use App\Models\Registrar;
use App\Services\Domain\Contracts\DomainRegistrarInterface;
use InvalidArgumentException;

class DomainRegistrarFactory
{
    public static function make(Registrar $registrar): DomainRegistrarInterface
    {
        return match ($registrar->provider) {
            'namecheap' => new NamecheapService($registrar),
            'resellbiz' => new ResellBizService($registrar),
            'liquid'    => new LiquidService($registrar),
            'dnama'     => new DnamaService($registrar),
            default     => throw new InvalidArgumentException("Provider [{$registrar->provider}] tidak dikenali."),
        };
    }
}