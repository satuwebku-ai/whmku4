<?php

namespace App\Services\Domain;

use App\Models\Registrar;
use App\Services\Domain\Contracts\DomainRegistrarInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

/**
 * Integrasi Namecheap lewat Namecheap API (XML response).
 * Dokumentasi resmi: https://www.namecheap.com/support/api/methods/
 *
 * Catatan penting Namecheap API:
 * - IP publik server/ClientIp yang dipakai WAJIB di-whitelist dulu di
 *   akun Namecheap (Profile » Tools » Namecheap API Access).
 * - Command "namecheap.domains.create" butuh data kontak WHOIS lengkap
 *   (Registrant/Tech/Admin/AuxBilling) — di sini keempatnya diisi dari
 *   data kontak yang sama demi kesederhanaan, sesuai praktik umum.
 */
class NamecheapService implements DomainRegistrarInterface
{
    public function __construct(protected Registrar $registrar) {}

    public function checkAvailability(array $domains): array
    {
        $response = $this->call('namecheap.domains.check', [
            'DomainList' => implode(',', $domains),
        ]);

        if (! $response['success']) {
            return ['success' => false, 'message' => $response['message'], 'results' => [], 'raw' => $response['raw']];
        }

        $results = [];
        $xml = $response['raw'];

        foreach ($xml->CommandResponse->DomainCheckResult ?? [] as $node) {
            $attrs = $node->attributes();
            $results[(string) $attrs->Domain] = ((string) $attrs->Available) === 'true';
        }

        return ['success' => true, 'message' => 'OK', 'results' => $results, 'raw' => $response['raw']];
    }

    public function registerDomain(array $params): array
    {
        [$sld, $tld] = $this->splitDomain($params['domain']);
        $c = $params['contact'];

        $contactFields = [];
        foreach (['Registrant', 'Tech', 'Admin', 'AuxBilling'] as $role) {
            $contactFields += [
                "{$role}FirstName"    => $c['first_name'],
                "{$role}LastName"     => $c['last_name'],
                "{$role}Address1"     => $c['address'],
                "{$role}City"         => $c['city'],
                "{$role}StateProvince" => $c['state'],
                "{$role}PostalCode"   => $c['postal_code'],
                "{$role}Country"      => $c['country'],
                "{$role}Phone"        => $c['phone'],
                "{$role}EmailAddress" => $c['email'],
            ];
        }

        return $this->call('namecheap.domains.create', array_merge([
            'DomainName' => $params['domain'],
            'Years'      => $params['years'] ?? 1,
        ], $contactFields));
    }

    public function renewDomain(string $domain, int $years): array
    {
        return $this->call('namecheap.domains.renew', [
            'DomainName' => $domain,
            'Years'      => $years,
        ]);
    }

    public function setNameservers(string $domain, array $nameservers): array
    {
        [$sld, $tld] = $this->splitDomain($domain);

        return $this->call('namecheap.domains.dns.setCustom', [
            'SLD'         => $sld,
            'TLD'         => $tld,
            'Nameservers' => implode(',', $nameservers),
        ]);
    }

    public function testConnection(): array
    {
        // Command ringan yang tidak mengubah data apapun, cukup untuk validasi kredensial + whitelist IP.
        return $this->call('namecheap.domains.gettldlist', []);
    }

    /**
     * Panggil Namecheap API (format XML response, autentikasi via query params).
     */
    protected function call(string $command, array $params): array
    {
        try {
            $response = Http::timeout(20)->get($this->registrar->api_base_url, array_merge([
                'ApiUser'  => $this->registrar->api_username,
                'ApiKey'   => $this->registrar->api_key,
                'UserName' => $this->registrar->username ?: $this->registrar->api_username,
                'ClientIp' => $this->registrar->client_ip,
                'Command'  => $command,
            ], $params));

            $xml = @simplexml_load_string($response->body());

            if (! $xml) {
                return ['success' => false, 'message' => 'Respons Namecheap tidak valid (bukan XML).', 'raw' => $response->body()];
            }

            $status = (string) $xml->attributes()->Status;

            if ($status !== 'OK') {
                $errorMessage = isset($xml->Errors->Error)
                    ? (string) $xml->Errors->Error
                    : 'Namecheap menolak permintaan.';

                return ['success' => false, 'message' => $errorMessage, 'raw' => $xml];
            }

            return ['success' => true, 'message' => 'OK', 'raw' => $xml];
        } catch (Throwable $e) {
            Log::warning("Namecheap API [{$command}] gagal: " . $e->getMessage(), ['registrar_id' => $this->registrar->id]);

            return [
                'success' => false,
                'message' => 'Tidak bisa terhubung ke Namecheap: ' . $e->getMessage(),
                'raw' => null,
            ];
        }
    }

    /**
     * Pisahkan "contoh.co.id" menjadi SLD "contoh" dan TLD "co.id".
     *
     * @return array{0: string, 1: string}
     */
    protected function splitDomain(string $domain): array
    {
        $parts = explode('.', $domain, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
