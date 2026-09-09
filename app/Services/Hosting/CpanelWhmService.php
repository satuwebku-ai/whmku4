<?php

namespace App\Services\Hosting;

use App\Models\Server;
use App\Services\Hosting\Contracts\HostingPanelInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Integrasi cPanel/WHM lewat WHM API 1 (whostmgr JSON API).
 * Dokumentasi resmi: https://api.docs.cpanel.net/whm/introduction/
 *
 * Autentikasi pakai API Token (bukan password root), dibuat dari:
 * WHM » Development » Manage API Tokens.
 */
class CpanelWhmService implements HostingPanelInterface
{
    public function __construct(protected Server $server) {}

    public function createAccount(array $params): array
    {
        $result = $this->call('createacct', [
            'username' => $params['username'],
            'domain'   => $params['domain'],
            'password' => $params['password'],
            'plan'     => $params['package'],
            'contactemail' => $params['email'] ?? '',
        ]);

        // WHM mengembalikan nameserver yang BENAR-BENAR dipakai akun ini
        // langsung di respons createacct -- ini sumber yang lebih akurat
        // daripada nameserver yang diisi manual di pengaturan Server,
        // karena ini persis apa yang WHM sungguhan tetapkan, bukan
        // ketikan admin yang bisa salah/ketinggalan zaman.
        $data = $result['raw']['data'] ?? [];
        $nameservers = array_values(array_filter([
            $data['nameserver'] ?? null,
            $data['nameserver2'] ?? null,
        ]));

        $result['nameservers'] = $nameservers ?: null;

        return $result;
    }

    public function suspendAccount(string $username, ?string $reason = null): array
    {
        return $this->call('suspendacct', [
            'user'   => $username,
            'reason' => $reason ?? 'Disuspend oleh admin panel',
        ]);
    }

    public function changePassword(string $username, string $password): array
    {
        return $this->call('passwd', [
            'user'     => $username,
            'password' => $password,
        ]);
    }

    public function unsuspendAccount(string $username): array
    {
        return $this->call('unsuspendacct', ['user' => $username]);
    }

    public function terminateAccount(string $username): array
    {
        return $this->call('removeacct', ['user' => $username]);
    }

    public function changePackage(string $username, string $package): array
    {
        return $this->call('changepackage', ['user' => $username, 'pkg' => $package]);
    }

    /**
     * Daftar paket cPanel yang BENAR-BENAR ada di server ini — lewat
     * WHM API 1 `listpkgs`, fungsi lama & stabil (sama tingkat
     * kestabilannya dengan createacct/suspendacct yang sudah kita pakai).
     * Dipakai di halaman Diagnosa supaya admin bisa cocokkan nama paket
     * yang diketik di form Produk dengan yang sungguhan ada di server —
     * ini sumber error paling sering (typo/beda huruf besar-kecil).
     */
    public function listAccounts(): array
    {
        $result = $this->call('listaccts', []);

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'], 'accounts' => [], 'raw' => $result['raw']];
        }

        $rows = $result['raw']['data']['acct'] ?? [];
        $rows = is_array($rows) ? $rows : [];

        return [
            'success' => true,
            'message' => 'OK',
            'accounts' => array_map(fn ($row) => [
                'domain' => $row['domain'] ?? null,
                'username' => $row['user'] ?? null,
                'package' => $row['plan'] ?? null,
                'suspended' => (bool) ($row['suspended'] ?? false),
            ], $rows),
            'raw' => $rows,
        ];
    }

    public function listPackages(): array
    {
        $result = $this->call('listpkgs', []);

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'], 'packages' => [], 'raw' => $result['raw']];
        }

        $rows = $result['raw']['data']['pkg'] ?? $result['raw']['package'] ?? [];
        $rows = is_array($rows) ? $rows : [];

        return [
            'success' => true,
            'message' => 'OK',
            'packages' => array_map(fn ($row) => $row['name'] ?? $row['pkgname'] ?? (string) $row, $rows),
            'raw' => $rows,
        ];
    }

    /**
     * Status SSL domain tertentu, lewat WHM API 1 `fetch_ssl_vhosts` —
     * fungsi ini sudah lama ada di WHM, tapi TIDAK sesering fungsi lain
     * yang sudah kita pakai (createacct, suspendacct, dst) saya
     * pastikan detail responsnya. Kalau field yang saya baca di sini
     * ternyata tidak cocok dengan punya server-mu, pakai
     * debugSslStatus() untuk lihat respons mentahnya, baru saya
     * perbaiki pemetaannya berdasarkan data sungguhan, bukan tebakan
     * kedua.
     */
    public function getSslStatus(string $domain): array
    {
        $result = $this->call('fetch_ssl_vhosts', []);

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'], 'installed' => null, 'expires_at' => null, 'issuer' => null, 'raw' => $result['raw']];
        }

        // Dicatat sementara -- supaya ketahuan bentuk ASLI respons WHM
        // untuk fungsi ini, tanpa perlu menebak-nebak lagi (sama seperti
        // yang berhasil membongkar bug lock/theft protection Liqu.id).
        Log::debug('WHM fetch_ssl_vhosts raw response', ['domain' => $domain, 'raw' => $result['raw']]);

        // Dikonfirmasi dari log nyata: daftar vhost ada di data.vhosts,
        // BUKAN langsung di data seperti asumsi sebelumnya -- itu
        // sebabnya domain tidak pernah ketemu dan selalu tertulis
        // "Belum Ada SSL" padahal sertifikatnya sudah aktif.
        $rows = $result['raw']['data']['vhosts'] ?? [];
        $rows = is_array($rows) ? $rows : [];

        $match = null;
        foreach ($rows as $row) {
            $rowDomain = $row['servername'] ?? null;
            if ($rowDomain === $domain) {
                $match = $row;
                break;
            }
        }

        if (! $match) {
            return ['success' => true, 'message' => 'Domain tidak ditemukan di daftar vhost SSL server.', 'installed' => false, 'expires_at' => null, 'issuer' => null, 'raw' => $rows];
        }

        // Dikonfirmasi dari log nyata: detail sertifikat ada di kunci
        // "crt" (bukan "certificate"), dan nama penerbitnya tersimpan
        // sebagai kunci datar "issuer.organizationName" (ada titiknya
        // literal di nama kunci, bukan objek bersarang).
        $cert = $match['crt'] ?? null;
        $notAfter = $cert['not_after'] ?? null;

        return [
            'success' => true,
            'message' => 'OK',
            'installed' => filled($notAfter),
            // not_after dari WHM berupa Unix timestamp mentah (mis.
            // 1794818199) -- diubah dulu jadi tanggal terbaca, supaya
            // tidak tampil sebagai angka mentah di halaman.
            'expires_at' => $notAfter ? \Carbon\Carbon::createFromTimestamp($notAfter)->format('d M Y') : null,
            'issuer' => $cert['issuer.organizationName'] ?? $cert['issuer.commonName'] ?? null,
            'raw' => $match,
        ];
    }

    /**
     * Alat bantu sementara — lihat persis respons mentah fetch_ssl_vhosts
     * dari server, kalau getSslStatus() di atas ternyata salah baca field.
     */
    public function debugSslStatus(): array
    {
        return $this->call('fetch_ssl_vhosts', []);
    }

    public function testConnection(): array
    {
        return $this->call('version', []);
    }

    /**
     * Pemakaian disk akun, lewat WHM API 1 `accountsummary`.
     *
     * Field `diskused`/`disklimit` sudah stabil bertahun-tahun di WHM
     * (contoh: "65M", "500M", atau "unlimited") — tidak diubah jadi angka
     * murni di sini supaya format aslinya (satuan M/G, atau "unlimited")
     * tetap apa adanya, bukan ditebak-tebak.
     *
     * Bandwidth SENGAJA tidak disertakan: WHM modern tidak selalu melacak
     * bandwidth per akun (fitur itu makin jarang dipakai host), jadi
     * daripada menampilkan angka yang belum tentu akurat, kolom itu
     * dilewatkan sampai ada cara yang lebih pasti untuk memastikannya.
     */
    public function getAccountUsage(string $username): array
    {
        $result = $this->call('accountsummary', ['user' => $username]);

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'], 'disk_used' => null, 'disk_limit' => null, 'raw' => $result['raw']];
        }

        $acct = $result['raw']['data']['acct'][0] ?? null;

        if (! $acct) {
            return ['success' => false, 'message' => 'Data akun tidak ditemukan di server.', 'disk_used' => null, 'disk_limit' => null, 'raw' => $result['raw']];
        }

        return [
            'success' => true,
            'message' => 'OK',
            'disk_used' => $acct['diskused'] ?? null,
            'disk_limit' => $acct['disklimit'] ?? null,
            'ip' => $acct['ip'] ?? null,
            'raw' => $acct,
        ];
    }

    /**
     * Buat sesi login cPanel sekali klik (Single Sign-On).
     *
     * WHM API 1 `create_user_session` mengembalikan URL berisi token
     * sekali pakai, sehingga klien bisa masuk ke cPanel tanpa perlu tahu
     * password akunnya. Token kedaluwarsa otomatis dalam beberapa menit.
     *
     * Dokumentasi: https://api.docs.cpanel.net/openapi/whm/operation/create_user_session/
     *
     * @return array{success: bool, message: string, url: ?string, raw: mixed}
     */
    /**
     * Login sekali klik ke WHM ITU SENDIRI (bukan akun cPanel klien) --
     * pakai user reseller/root yang sudah tersimpan di server ini.
     * create_user_session ternyata juga mendukung service=whostmgrd,
     * bukan cuma cpaneld, dan tetap bisa pakai autentikasi API Token
     * yang sama (tidak perlu simpan password WHM terpisah).
     */
    public function createWhmSsoSession(): array
    {
        return $this->createSsoSession($this->server->api_username, 'whostmgrd');
    }

    public function createSsoSession(string $username, string $service = 'cpaneld', ?string $path = null): array
    {
        $result = $this->call('create_user_session', ['user' => $username, 'service' => $service]);

        $url = $result['raw']['data']['url'] ?? null;

        if (! $result['success'] || ! $url) {
            return [
                'success' => false,
                'message' => $result['message'] ?: 'Server tidak mengembalikan URL sesi.',
                'url' => null,
                'raw' => $result['raw'],
            ];
        }

        // PENTING (ditemukan lewat log diagnostik 21 Agu 2026): URL sesi
        // dari create_user_session berbentuk
        //   /cpsessXXXX/login/?session=TOKEN
        // -- path "/login/" itu BUKAN sekadar bagian URL, itu endpoint
        // yang benar-benar memvalidasi TOKEN sekali-pakai dan membuat
        // cookie sesi login. Pendekatan sebelumnya MENIMPA "/login/"
        // dengan path tujuan (mis. frontend/jupiter/...), sehingga token
        // tidak pernah divalidasi sama sekali -- itulah kenapa Akses
        // Cepat selalu gagal "tidak bisa login", padahal tombol utama
        // (tanpa path) berhasil karena TIDAK menyentuh "/login/".
        //
        // Perbaikan: biarkan "/login/?session=..." tetap utuh, tambahkan
        // tujuan halaman lewat parameter goto_uri di URL YANG SAMA --
        // ini didukung cPanel untuk mengarahkan ke halaman tertentu
        // SETELAH proses autentikasi selesai, bukan menggantikannya.
        if ($path) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . 'goto_uri=' . urlencode('/' . ltrim($path, '/'));

            Log::info('SSO goto_uri appended', ['requested_path' => $path, 'url' => $url]);
        }

        return ['success' => true, 'message' => 'OK', 'url' => $url, 'raw' => $result['raw']];
    }

    /**
     * Panggil WHM API 1 dengan autentikasi token.
     * Format header: Authorization: whm {api_username}:{api_token}
     */
    protected function call(string $function, array $params): array
    {
        try {
            $response = $this->client()
                ->get("/json-api/{$function}", array_merge($params, ['api.version' => 1]));

            $body = $response->json();
            $result = $body['metadata'] ?? null;

            $success = $response->successful()
                && (($result['result'] ?? null) === 1 || ($result['reason'] ?? null) === 'OK');

            return [
                'success' => $success,
                'message' => $result['reason'] ?? ($success ? 'Berhasil.' : 'Panel menolak permintaan (respons tidak dikenali).'),
                'raw'     => $body,
            ];
        } catch (Throwable $e) {
            Log::warning("WHM API [{$function}] gagal: " . $e->getMessage(), ['server_id' => $this->server->id]);

            return [
                'success' => false,
                'message' => 'Tidak bisa terhubung ke server WHM: ' . $e->getMessage(),
                'raw'     => null,
            ];
        }
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->server->api_base_url)
            ->withHeaders([
                'Authorization' => "whm {$this->server->api_username}:{$this->server->api_token}",
            ])
            ->withOptions(['verify' => $this->server->verify_ssl])
            ->timeout(15);
    }
}
