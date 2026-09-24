<?php

namespace App\Services\Vps;

use App\Models\Server;
use App\Services\Vps\Contracts\VpsProviderInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/** DigitalOcean adapter. Provider-specific values are read from package JSON:
 * size (slug), image (id/slug), region. The controller remains provider-neutral.
 */
class DigitalOceanVpsProvider implements VpsProviderInterface
{
    public function __construct(private readonly Server $server) {}

    private function client(int $timeout = 60)
    {
        return Http::withToken($this->server->api_token)
            ->acceptJson()->baseUrl('https://api.digitalocean.com/v2')->timeout($timeout);
    }

    private function result($response, string $ok = 'OK'): array
    {
        $body = $response->json();
        if ($response->successful()) return ['success' => true, 'message' => $ok, 'raw' => $body];
        return ['success' => false, 'message' => $body['message'] ?? ('Provider menolak HTTP '.$response->status()), 'raw' => $body];
    }

    public function create(array $params): array
    {
        $spec = is_array($params['package'] ?? null) ? $params['package'] : (json_decode((string) ($params['package'] ?? '{}'), true) ?: []);
        $name = $params['domain'] ?? $params['name'] ?? 'vps';
        $region = $spec['region'] ?? $spec['location'] ?? null;
        $size = $spec['size'] ?? $spec['provider_size'] ?? null;
        $image = $spec['image'] ?? $spec['provider_image_id'] ?? null;
        if (! $region || ! $size || ! $image) {
            return ['success' => false, 'message' => 'DigitalOcean membutuhkan package.region, package.size/provider_size, dan package.image/provider_image_id.', 'raw' => null];
        }
        try {
            $response = $this->client(120)->post('/droplets', array_filter([
                'name' => $name, 'region' => $region, 'size' => $size, 'image' => is_numeric($image) ? (int) $image : $image,
            ], fn($v) => $v !== null));
            $r = $this->result($response, 'Droplet berhasil dibuat.');
            if (! $r['success']) return $r;
            $d = $r['raw']['droplet'] ?? [];
            return $r + ['username' => isset($d['id']) ? (string) $d['id'] : null, 'ip' => collect($d['networks']['v4'] ?? [])->firstWhere('type', 'public')['ip_address'] ?? null];
        } catch (Throwable $e) { return ['success' => false, 'message' => 'Tidak bisa terhubung ke DigitalOcean: '.$e->getMessage(), 'raw' => null]; }
    }

    public function get(string $id): array { return $this->result($this->client()->get('/droplets/'.urlencode($id))); }
    private function action(string $id, string $type, array $extra = []): array { return $this->result($this->client()->post('/droplets/'.urlencode($id).'/actions', ['type' => $type] + $extra), 'Perintah berhasil dikirim.'); }
    public function start(string $id): array { return $this->action($id, 'power_on'); }
    public function stop(string $id, bool $force = false): array { return $this->action($id, $force ? 'power_off' : 'shutdown'); }
    public function restart(string $id): array { return $this->action($id, 'reboot'); }
    public function delete(string $id): array { return $this->result($this->client()->delete('/droplets/'.urlencode($id)), 'Droplet berhasil dihapus.'); }
    public function changePassword(string $id, string $username, string $password): array { return $this->action($id, 'password_reset'); }
    public function reinstall(string $id, ?string $osName = null, ?string $osVersion = null): array
    {
        return ['success' => false, 'message' => 'Reinstall DigitalOcean membutuhkan image ID baru. Simpan provider_image_id pada package lalu gunakan adapter khusus rebuild.', 'raw' => null];
    }
    public function resize(string $id, array $spec): array
    {
        $size = $spec['size'] ?? $spec['provider_size'] ?? null;
        if (! $size) return ['success' => false, 'message' => 'provider_size/size wajib diisi untuk resize DigitalOcean.', 'raw' => null];
        return $this->action($id, 'resize', ['size' => $size, 'disk' => false]);
    }
    public function testConnection(): array { return $this->result($this->client(20)->get('/account'), 'Koneksi DigitalOcean berhasil.'); }
    public function images(): array { return $this->result($this->client()->get('/images', ['type' => 'distribution', 'per_page' => 200])); }
    public function locations(): array { return $this->result($this->client()->get('/regions', ['per_page' => 200])); }
    public function pools(): array { return ['success' => true, 'message' => 'DigitalOcean tidak menggunakan pool seperti IDCloudHost.', 'raw' => []]; }
    public function parameters(): array { return ['success' => true, 'message' => 'Gunakan size slug provider untuk DigitalOcean.', 'raw' => []]; }

    /**
     * Harga modal DigitalOcean = harga per SIZE (paket tetap), dalam USD.
     * Disimpan lengkap per slug supaya spek produk (vCPU/RAM/disk) bisa
     * diikutkan ke size yang dipilih dan tarif markup dihitung dari harga
     * size itu. Kurs ke Rupiah diisi admin di halaman Server.
     */
    public function pricing(): array
    {
        try {
            $sizes = [];
            $page = 1;

            do {
                $r = $this->result($this->client(30)->get('/sizes', ['per_page' => 200, 'page' => $page]));

                if (! $r['success']) {
                    return ['success' => false, 'message' => $r['message'], 'cost' => null];
                }

                foreach (($r['raw']['sizes'] ?? []) as $size) {
                    if (($size['available'] ?? true) === false || empty($size['slug'])) {
                        continue;
                    }

                    $sizes[$size['slug']] = [
                        'hourly'  => (float) ($size['price_hourly'] ?? 0),
                        'monthly' => (float) ($size['price_monthly'] ?? 0),
                        'vcpu'    => (int) ($size['vcpus'] ?? 0),
                        'ram'     => (int) ($size['memory'] ?? 0), // MB
                        'disk'    => (int) ($size['disk'] ?? 0),   // GB
                    ];
                }

                $hasNext = ! empty($r['raw']['links']['pages']['next']);
                $page++;
            } while ($hasNext && $page <= 10);

            $regions = [];
            $rr = $this->result($this->client(30)->get('/regions', ['per_page' => 200]));

            foreach (($rr['success'] ? ($rr['raw']['regions'] ?? []) : []) as $region) {
                if (($region['available'] ?? true) && ! empty($region['slug'])) {
                    $regions[$region['slug']] = $region['name'] ?? $region['slug'];
                }
            }
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Tidak bisa terhubung ke DigitalOcean: ' . $e->getMessage(), 'cost' => null];
        }

        if (! $sizes) {
            return ['success' => false, 'message' => 'DigitalOcean tidak mengembalikan daftar size.', 'cost' => null];
        }

        return [
            'success' => true,
            'message' => 'OK',
            'cost'    => ['model' => 'size', 'currency' => 'USD', 'sizes' => $sizes, 'regions' => $regions],
        ];
    }
}
