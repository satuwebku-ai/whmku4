<?php

namespace App\Support;

/**
 * Nonce CSP per-request. Didaftarkan sebagai singleton supaya nilai yang
 * dicetak di Blade (@nonce) sama dengan yang dikirim di header CSP.
 */
class CspNonce
{
    private ?string $value = null;

    public function value(): string
    {
        return $this->value ??= rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }
}
