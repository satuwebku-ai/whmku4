<?php

namespace App\Services\Domain;

use App\Models\Registrar;
use App\Services\Domain\Contracts\DomainRegistrarInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Integrasi Liqu.id (registrar software JogjaCamp / ResellerCamp).
 *
 * Endpoint & parameter di kelas ini diambil dari library PHP resmi
 * Liquid (github.com/liquidregistrar/liquid-php) dan dokumentasi
 * liquid-docs.readthedocs.io — bukan tebakan.
 *
 * ── Ringkasan API ──
 * Base URL   : https://api.liqu.id/v1          (produksi)
 *              https://api.domainsas.com/v1    (demo/sandbox)
 * Auth       : HTTP Basic Auth
 *              username = Reseller ID, password = API Key
 * Format     : JSON di semua respons (termasuk error)
 * Rate limit : ±100 request / 15 menit per reseller
 *
 * ── Catatan penting soal alur registrasi ──
 * Berbeda dari Namecheap yang menerima data kontak WHOIS mentah dalam
 * satu request, Liqu.id memakai model berjenjang:
 *
 *     customer  →  contact  →  domain
 *
 * Artinya untuk registrasi domain dibutuhkan `customer_id` dan empat
 * contact ID (registrant/billing/admin/tech). Service ini menanganinya
 * otomatis: kalau customer/contact belum ada, akan dibuatkan dulu dari
 * data kontak yang dikirim, baru domainnya didaftarkan.
 */
class LiquidService implements DomainRegistrarInterface
{
    /**
     * Base URL default kalau field "API URL" di form Registrar dikosongkan.
     */
    /**
     * Endpoint harga mengembalikan ratusan TLD sekaligus sehingga jauh
     * lebih lambat dari request biasa — timeout dibuat lebih longgar.
     */
    protected const PRICE_TIMEOUT = 90;

    protected const DEFAULT_LIVE_URL = 'https://api.liqu.id/v1';
    protected const DEFAULT_DEMO_URL = 'https://api.domainsas.com/v1';

    public function __construct(protected Registrar $registrar) {}

    /**
     * GET /domains/availability?domain=a.com,b.com
     */
    /**
     * Berapa domain yang dikirim per request.
     *
     * Endpoint availability memakai GET, sehingga seluruh daftar domain
     * masuk ke URL. Mengirim ratusan sekaligus membuat URL terlalu panjang
     * dan ditolak server dengan HTTP 414, jadi permintaan dipecah.
     */
    protected const AVAILABILITY_CHUNK = 15;

    public function checkAvailability(array $domains): array
    {
        $results = [];
        $lastRaw = null;
        $errors = [];

        foreach (array_chunk(array_values($domains), self::AVAILABILITY_CHUNK) as $chunk) {
            $response = $this->call('get', '/domains/availability', [
                'domain' => implode(',', $chunk),
            ]);

            if ($response['success']) {
                $lastRaw = $response['raw'];
                $results += $this->parseAvailability($response['raw'], $chunk);
                continue;
            }

            // Registrar menolak SATU batch penuh kalau ada satu saja ekstensi
            // yang tidak didukung (mis. invalid_argument). Tanpa penanganan
            // ini, satu TLD bermasalah membuat seluruh pencarian gagal —
            // jadi batch yang gagal diulang satu per satu supaya ekstensi
            // yang baik-baik saja tetap muncul hasilnya.
            $recovered = 0;

            foreach ($chunk as $single) {
                $retry = $this->call('get', '/domains/availability', ['domain' => $single]);

                if (! $retry['success']) {
                    $errors[$single] = $retry['message'];
                    continue;
                }

                $lastRaw = $retry['raw'];
                $parsed = $this->parseAvailability($retry['raw'], [$single]);

                if ($parsed) {
                    $results += $parsed;
                    $recovered++;
                }
            }

            if ($recovered === 0) {
                $errors['_batch'] = $response['message'];
            }
        }

        // Semua batch gagal — tidak ada yang bisa ditampilkan.
        if (empty($results) && $errors) {
            return [
                'success' => false,
                'message' => reset($errors),
                'results' => [],
                'raw' => $lastRaw,
            ];
        }

        // Sebagian berhasil: hasilnya tetap ditampilkan, ekstensi yang gagal
        // dicatat di log agar bisa dinonaktifkan dari halaman pencarian.
        if ($errors) {
            Log::info('Liqu.id: sebagian ekstensi gagal dicek.', [
                'registrar_id' => $this->registrar->id,
                'gagal' => array_keys($errors),
            ]);
        }

        $response = ['raw' => $lastRaw];

        // Format respons endpoint availability tidak didokumentasikan Liqu.id,
        // jadi parser di bawah menangani beberapa kemungkinan bentuk. Kalau
        // tidak ada yang cocok, respons mentahnya dicatat supaya bisa
        // dicocokkan — lihat storage/logs/laravel.log.
        if (empty($results)) {
            Log::warning('Liqu.id: respons availability tidak dikenali formatnya.', [
                'registrar_id' => $this->registrar->id,
                'requested' => $domains,
                'raw_response' => $response['raw'],
            ]);
        }

        return [
            'success' => true,
            'message' => empty($results)
                ? 'Terhubung ke Liqu.id, tapi format respons belum dikenali. Cek storage/logs/laravel.log untuk melihat respons mentahnya.'
                : 'OK',
            'results' => $results,
            'raw' => $response['raw'],
        ];
    }

    /**
     * POST /domains
     *
     * Butuh customer_id + 4 contact ID, jadi dibuatkan dulu bila belum ada.
     */
    public function registerDomain(array $params): array
    {
        $c = $params['contact'];

        // 1. Pastikan customer ada (dicari berdasarkan email, dibuat kalau belum ada)
        $customer = $this->resolveCustomerId($c);

        if (! $customer['success']) {
            return ['success' => false, 'message' => 'Gagal menyiapkan customer di Liqu.id: ' . $customer['message'], 'raw' => $customer['raw']];
        }

        $customerId = $customer['customer_id'];

        // 2. Buat contact untuk customer tersebut
        $contact = $this->createContact($customerId, $c);

        if (! $contact['success']) {
            return ['success' => false, 'message' => 'Gagal membuat contact di Liqu.id: ' . $contact['message'], 'raw' => $contact['raw']];
        }

        $contactId = $contact['contact_id'];

        // 2b. Untuk TLD yang mewajibkan data kelayakan (lihat
        //     ELIGIBILITY_REQUIRED_TLDS), harus dikirim ke endpoint
        //     KHUSUS ini dulu sebelum domain didaftarkan — kalau
        //     dilewati, registry aslinya (bukan Liqu.id) akan menolak
        //     pendaftarannya.
        if (! empty($params['eligibility_criteria']) && ! empty($params['eligibility_extra'])) {
            $eligibility = $this->updateContactEligibility($customerId, $contactId, $params['eligibility_criteria'], $params['eligibility_extra']);

            if (! $eligibility['success']) {
                return ['success' => false, 'message' => 'Gagal mengirim data kelayakan domain: ' . $eligibility['message'], 'raw' => $eligibility['raw']];
            }
        }

        // 3. Daftarkan domain. Keempat peran contact memakai contact yang sama,
        //    praktik umum untuk registrasi standar.
        $payload = [
            'domain_name'           => $params['domain'],
            'customer_id'           => $customerId,
            'registrant_contact_id' => $contactId,
            'billing_contact_id'    => $contactId,
            'admin_contact_id'      => $contactId,
            'tech_contact_id'       => $contactId,
            'invoice_option'        => $params['invoice_option'] ?? 'no_invoice',
            'years'                 => $params['years'] ?? 1,
        ];

        if (! empty($params['nameservers'])) {
            $payload['ns'] = implode(',', (array) $params['nameservers']);
        }

        if (! empty($params['whois_privacy'])) {
            $payload['purchase_privacy_protection'] = 'true';
            $payload['privacy_protection_enabled'] = 'true';
        }

        return $this->call('post', '/domains', $payload);
    }

    /**
     * POST /domains/transfer — pindahkan domain dari registrar lain ke
     * akun Liqu.id ini. Butuh kode EPP/Auth dari registrar lama, yang
     * pemilik domain harus minta sendiri di sana (bukan sesuatu yang bisa
     * kita dapatkan otomatis — itu justru untuk domain yang SUDAH ada di
     * Liqu.id, lihat getAuthCode()).
     *
     * Sesuai spesifikasi resmi: proses transfer BUKAN langsung pindah
     * detik itu juga — ada persetujuan dari pemilik domain (email dari
     * registrar lama) dan biasanya makan waktu 5-7 hari. Status domain
     * di sistem kita tetap "pending" sampai admin memastikan transfer-nya
     * sudah benar-benar selesai.
     */
    public function transferDomain(array $params): array
    {
        $c = $params['contact'];

        $customer = $this->resolveCustomerId($c);

        if (! $customer['success']) {
            return ['success' => false, 'message' => 'Gagal menyiapkan customer di Liqu.id: ' . $customer['message'], 'raw' => $customer['raw']];
        }

        $customerId = $customer['customer_id'];
        $contact = $this->createContact($customerId, $c);

        if (! $contact['success']) {
            return ['success' => false, 'message' => 'Gagal membuat contact di Liqu.id: ' . $contact['message'], 'raw' => $contact['raw']];
        }

        $contactId = $contact['contact_id'];

        $payload = [
            'domain_name'           => $params['domain'],
            'customer_id'           => $customerId,
            'registrant_contact_id' => $contactId,
            'admin_contact_id'      => $contactId,
            'billing_contact_id'    => $contactId,
            'tech_contact_id'       => $contactId,
            'auth_code'             => $params['auth_code'] ?? '',
            'years'                 => $params['years'] ?? 1,
            'invoice_option'        => $params['invoice_option'] ?? 'no_invoice',
        ];

        if (! empty($params['nameservers'])) {
            $payload['ns'] = implode(',', (array) $params['nameservers']);
        }

        if (! empty($params['whois_privacy'])) {
            $payload['purchase_privacy_protection'] = 'true';
        }

        return $this->call('post', '/domains/transfer', $payload);
    }

    /**
     * POST /domains/{domain_id}/renew
     *
     * Liqu.id memakai domain_id (numerik), bukan nama domain — jadi
     * dicari dulu ID-nya lewat /domains/details-by-name.
     */
    public function renewDomain(string $domain, int $years): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('post', "/domains/{$lookup['domain_id']}/renew", [
            'years'          => $years,
            'current_date'   => now()->format('Y-m-d'),
            'invoice_option' => 'no_invoice',
        ]);
    }

    /**
     * PUT /domains/{domain_id}/ns
     */
    public function setNameservers(string $domain, array $nameservers): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('put', "/domains/{$lookup['domain_id']}/ns", [
            'ns' => implode(',', $nameservers),
        ]);
    }

    /**
     * Uji kredensial dengan request ringan yang tidak mengubah data.
     */
    public function testConnection(): array
    {
        $result = $this->call('get', '/domains', ['limit' => 1]);

        if ($result['success']) {
            $result['message'] = 'Kredensial Liqu.id valid.';
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────
    // Registrar Lock (kunci transfer)
    // ─────────────────────────────────────────────────────────────

    public function getDomainLockStatus(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'locked' => null, 'raw' => $lookup['raw']];
        }

        $result = $this->call('get', "/domains/{$lookup['domain_id']}/locked");

        // Dikonfirmasi dari log: endpoint ini mengembalikan boolean POLOS
        // ("true"/"false"), BUKAN objek {"locked": true} seperti endpoint
        // Liqu.id lainnya -- makanya firstRow() (yang mengharapkan array)
        // selalu gagal membacanya. Dibaca langsung sebagai boolean di sini.
        $locked = filter_var($result['raw'], FILTER_VALIDATE_BOOLEAN);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'locked'  => $locked,
            'raw'     => $result['raw'],
        ];
    }

    public function lockDomain(string $domain, ?string $reason = null): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('put', "/domains/{$lookup['domain_id']}/locked", [
            'reason' => $reason ?: 'Dikunci oleh klien lewat panel.',
        ]);
    }

    public function unlockDomain(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('delete', "/domains/{$lookup['domain_id']}/locked");
    }

    /**
     * GET /resellers/{reseller_id} — detail akun reseller ini, termasuk
     * `selling_currency` (USD/IDR). Berguna untuk memastikan satuan mata
     * uang yang dipakai akun, karena endpoint harga & saldo mengembalikan
     * angka polos tanpa keterangan mata uang sama sekali.
     */
    public function getAccountDetails(): array
    {
        $resellerId = $this->registrar->api_username;

        $result = $this->call('get', "/resellers/{$resellerId}");
        $row = $this->firstRow($result['raw']);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'selling_currency' => $row['selling_currency'] ?? null,
            'name' => $row['name'] ?? null,
            'company' => $row['company'] ?? null,
            'raw' => $result['raw'],
        ];
    }

    /**
     * GET /customers — daftar customer yang sudah pernah dibuat di akun
     * ini, terurut dari yang terbaru. Berguna untuk melihat sisa data
     * percobaan domain sebelumnya (mis. "Satuwebku" / "fahri alhaddar"
     * yang dipakai testing hosting kemarin, kalau kebetulan juga sempat
     * dipakai coba daftar domain).
     */
    public function listCustomers(int $limit = 20): array
    {
        $result = $this->call('get', '/customers', ['limit' => $limit, 'page_no' => 1]);

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'], 'customers' => [], 'raw' => $result['raw']];
        }

        $rows = $result['raw']['data'] ?? $result['raw'] ?? [];
        $rows = is_array($rows) ? $rows : [];

        return [
            'success' => true,
            'message' => 'OK',
            'customers' => array_map(fn ($row) => [
                'id' => $row['customer_id'] ?? $row['id'] ?? null,
                'name' => $row['name'] ?? null,
                'email' => $row['email'] ?? null,
                'company' => $row['company'] ?? null,
            ], $rows),
            'raw' => $rows,
        ];
    }

    /**
     * GET /account/prices — daftar harga yang berlaku untuk akun ini.
     * Dipakai di alat diagnosa untuk melihat format angka mentahnya.
     */
    public function getAccountPricesRaw(): array
    {
        return $this->call('get', '/account/prices');
    }

    // ─────────────────────────────────────────────────────────────
    // Saldo Akun
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /account/balance — saldo deposit akun reseller ini di Liqu.id.
     * Berguna dicek dari admin panel langsung, tanpa perlu login
     * terpisah ke dashboard Liqu.id cuma untuk pastikan saldo cukup
     * sebelum ada klien yang mau daftar/perpanjang domain.
     */
    public function getAccountBalance(): array
    {
        $result = $this->call('get', '/account/balance');

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'], 'balance' => null, 'currency' => null, 'raw' => $result['raw']];
        }

        // Beda dari kebanyakan endpoint lain, respons ini TIDAK dibungkus
        // objek/array — Liqu.id mengembalikan angka polos apa adanya
        // (contoh nyata: 6.13), jadi firstRow() (yang cuma paham
        // array/objek) akan selalu gagal untuk endpoint ini secara
        // spesifik. Ditangani terpisah di sini.
        $raw = $result['raw'];
        $balance = is_numeric($raw) ? (float) $raw : $this->firstRow($raw)['balance'] ?? null;

        if ($balance === null) {
            return ['success' => false, 'message' => 'Format respons tidak dikenali.', 'balance' => null, 'currency' => null, 'raw' => $raw];
        }

        return [
            'success'  => true,
            'message'  => 'OK',
            'balance'  => $balance,
            // API ini TIDAK menyertakan info mata uang sama sekali (cuma
            // angka polos) — jadi sengaja tidak ditebak "IDR" begitu saja
            // seperti sebelumnya (bisa saja akunnya USD, seperti pola yang
            // terlihat di reseller lain). Biarkan null, tampilan yang
            // memakai ini WAJIB meminta admin konfirmasi sendiri.
            'currency' => null,
            'raw'      => $raw,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // EPP / Auth Code
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /domains/{domain_id}/auth_code — kode transfer domain.
     *
     * Beberapa TLD (mis. .id) tidak memakai skema auth code — Liqu.id
     * akan mengembalikan pesan/kode error yang jelas untuk kasus itu,
     * diteruskan apa adanya ke pemanggil.
     */
    public function getAuthCode(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'code' => null, 'raw' => $lookup['raw']];
        }

        $result = $this->call('get', "/domains/{$lookup['domain_id']}/auth_code");

        // Sama seperti endpoint /locked dan /theft_protection: Liqu.id
        // mengembalikan NILAINYA LANGSUNG (string kode transfer polos),
        // bukan objek {"auth_code": "..."} -- firstRow() yang
        // mengharapkan array selalu gagal membacanya, dan kode transfer
        // yang sebenarnya berhasil diambil malah dianggap gagal.
        $raw = $result['raw'];
        $code = null;

        if (is_string($raw) && trim($raw) !== '') {
            $code = trim($raw, " \t\n\r\0\x0B\"");
        } elseif (is_array($raw)) {
            $row = $this->firstRow($raw);
            $code = $row['auth_code'] ?? $row['secret'] ?? null;
        }

        return [
            'success' => $result['success'] && filled($code),
            'message' => filled($code) ? 'OK' : ($result['message'] ?: 'Kode transfer tidak dikembalikan registrar.'),
            'code'    => $code,
            'raw'     => $result['raw'],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Privacy Protection (ID Protection / WHOIS Privacy)
    // ─────────────────────────────────────────────────────────────

    public function getPrivacyProtection(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'enabled' => null, 'raw' => $lookup['raw']];
        }

        $result = $this->call('get', "/domains/{$lookup['domain_id']}/privacy_protection");

        // Endpoint status per-domain di Liqu.id mengembalikan boolean
        // POLOS (pola yang sama dengan /locked, /theft_protection, dan
        // /auth_code). Bentuk objek tetap ditangani sebagai cadangan
        // kalau suatu saat formatnya berubah.
        $raw = $result['raw'];

        if (is_array($raw)) {
            $row = $this->firstRow($raw);
            $enabled = filter_var($row['privacy_protection_enabled'] ?? $row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        } else {
            $enabled = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'enabled' => $enabled,
            'raw'     => $result['raw'],
        ];
    }

    public function enablePrivacyProtection(string $domain): array
    {
        // Spesifikasi resmi Liqu.id (api.liqu.id/docs) mengonfirmasi method
        // aktifkan adalah PUT — bukan POST seperti dugaan awal dari
        // library PHP mereka, yang ternyata sedikit berbeda dari API
        // sungguhan. Kalau tetap POST, permintaan ini akan ditolak diam-diam.
        return $this->togglePrivacyProtection($domain, 'put');
    }

    public function disablePrivacyProtection(string $domain): array
    {
        return $this->togglePrivacyProtection($domain, 'delete');
    }

    private function togglePrivacyProtection(string $domain, string $method): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call($method, "/domains/{$lookup['domain_id']}/privacy_protection");
    }

    // ─────────────────────────────────────────────────────────────
    // DNS Records
    // ─────────────────────────────────────────────────────────────

    /**
     * Peta jenis record yang didukung Liqu.id ke segmen URL-nya.
     * Dibatasi ke jenis yang paling umum dipakai klien hosting — Liqu.id
     * juga mendukung SRV dan child-NS, tapi keduanya jarang dibutuhkan
     * dan menambah kerumitan form tanpa manfaat sepadan untuk sekarang.
     */
    public const DNS_TYPES = [
        'A'     => 'ip',
        'AAAA'  => 'ipv6',
        'CNAME' => 'cname',
        'MX'    => 'mx',
        'TXT'   => 'txt',
    ];

    /**
     * Ambil semua record DNS (gabungan tiap jenis, karena Liqu.id
     * memisahkan endpoint per jenis record, tidak ada satu endpoint
     * "semua record sekaligus").
     */
    public function listDnsRecords(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'records' => [], 'raw' => $lookup['raw']];
        }

        $records = [];
        $errors = [];

        foreach (self::DNS_TYPES as $type => $segment) {
            $result = $this->call('get', "/domains/{$lookup['domain_id']}/dns/{$segment}");

            if (! $result['success']) {
                $errors[] = "{$type}: {$result['message']}";
                continue;
            }

            foreach ((array) $result['raw'] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $records[] = [
                    'type'     => $type,
                    'hostname' => $row['hostname'] ?? '@',
                    'value'    => $row['value'] ?? '',
                    'priority' => $row['priority'] ?? null,
                ];
            }
        }

        // Sebagian jenis gagal diambil bukan berarti semuanya gagal —
        // tampilkan yang berhasil, catat yang gagal supaya tidak diam-diam
        // terlihat seperti memang tidak ada record jenis itu.
        return [
            'success' => empty($errors) || ! empty($records),
            'message' => $errors ? implode('; ', $errors) : 'OK',
            'records' => $records,
            'raw'     => null,
        ];
    }

    public function addDnsRecord(string $domain, string $type, string $hostname, string $value, ?int $priority = null): array
    {
        $segment = self::DNS_TYPES[$type] ?? null;

        if (! $segment) {
            return ['success' => false, 'message' => "Jenis record {$type} tidak didukung.", 'raw' => null];
        }

        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        $payload = ['hostname' => $hostname, 'value' => $value];

        if ($type === 'MX') {
            $payload['priority'] = $priority ?? 10;
        }

        return $this->call('post', "/domains/{$lookup['domain_id']}/dns/{$segment}", $payload);
    }

    public function deleteDnsRecord(string $domain, string $type, string $hostname, string $value): array
    {
        $segment = self::DNS_TYPES[$type] ?? null;

        if (! $segment) {
            return ['success' => false, 'message' => "Jenis record {$type} tidak didukung.", 'raw' => null];
        }

        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        // Hostname/value dijadikan bagian URL persis seperti dokumentasi
        // resmi — endpoint delete Liqu.id memang berbasis path, bukan
        // body atau query string.
        return $this->call('delete', "/domains/{$lookup['domain_id']}/dns/{$segment}/" . rawurlencode($hostname) . '/' . rawurlencode($value));
    }

    /**
     * GET /tlds — daftar TLD yang tersedia di akun reseller.
     * GET /resellers/prices — harga modal per TLD.
     *
     * Dipakai fitur "Sinkronkan TLD" supaya tidak perlu input manual.
     *
     * @return array{success: bool, message: string, tlds: array, raw: mixed}
     */
    public function listTlds(): array
    {
        $response = $this->call('get', '/tlds');

        if (! $response['success']) {
            return ['success' => false, 'message' => $response['message'], 'tlds' => [], 'raw' => $response['raw']];
        }

        $tlds = [];

        // Format asli Liqu.id: objek dengan key kode internal, nama TLD ada
        // di field "label".
        //   { "domcno": { "label": ".COM", "min_duration": "1 years", ... },
        //     "dotcoid": { "label": ".CO.ID", ... } }
        foreach ((array) $response['raw'] as $key => $row) {
            // Bentuk sederhana: array of string.
            if (is_string($row)) {
                $tlds[] = ['extension' => $this->normalizeExtension($row), 'price' => null, 'min_years' => 1, 'max_years' => 10];
                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $name = $row['label'] ?? $row['tld'] ?? $row['name'] ?? $row['extension'] ?? null;

            if (! $name) {
                continue;
            }

            $tlds[] = [
                'extension' => $this->normalizeExtension((string) $name),
                'price'     => $row['price'] ?? $row['register_price'] ?? null,
                // Field durasi berformat "10 years" — ambil angkanya saja.
                'min_years' => $this->parseYears($row['min_duration'] ?? null, 1),
                'max_years' => $this->parseYears($row['max_duration'] ?? null, 10),
            ];
        }

        if (empty($tlds)) {
            Log::warning('Liqu.id: respons /tlds tidak dikenali formatnya.', [
                'registrar_id' => $this->registrar->id,
                'raw_response' => $response['raw'],
            ]);

            return [
                'success' => false,
                'message' => 'Terhubung, tapi format daftar TLD belum dikenali. Cek storage/logs/laravel.log.',
                'tlds' => [],
                'raw' => $response['raw'],
            ];
        }

        // Lengkapi harga modal kalau endpoint prices tersedia.
        $prices = $this->call('get', '/resellers/prices');

        if ($prices['success'] && is_array($prices['raw'])) {
            $priceMap = [];

            foreach ($prices['raw'] as $key => $row) {
                if (is_array($row)) {
                    $name = $row['tld'] ?? $row['name'] ?? (is_string($key) ? $key : null);
                    $value = $row['register'] ?? $row['registration'] ?? $row['price'] ?? null;

                    if ($name && $value !== null) {
                        $priceMap[$this->normalizeExtension((string) $name)] = (float) $value;
                    }
                }
            }

            foreach ($tlds as &$tld) {
                $tld['price'] ??= $priceMap[$tld['extension']] ?? null;
            }
            unset($tld);
        }

        return ['success' => true, 'message' => 'OK', 'tlds' => $tlds, 'raw' => $response['raw']];
    }

    /**
     * Samakan format jadi berawalan titik: "com" → ".com"
     */
    /**
     * Ambil harga modal (cost price) dari Liqu.id.
     *
     * Struktur API-nya terpecah dua dan harus digabung:
     *
     *  1. GET /account/prices  — harga modal kita, tapi dikunci dengan
     *     kode internal. Contoh entri:
     *       "com": {
     *         "price_new_conv": "163.44",     ← IDR dalam RIBUAN
     *         "price_renew_conv": "163.44",
     *         "price_transfer_conv": "163.44",
     *         "tld_name": "domcno", "tld_key": "com"
     *       }
     *
     *  2. GET /resellers/prices — memuat nama ekstensi sesungguhnya:
     *       "domcno": [{ "tld_label": ".COM", "tld_pref": "Sell" }]
     *
     * Tanpa endpoint kedua, ekstensi bertingkat seperti ".co.id" tidak
     * bisa dikenali (kode internalnya "dotcoid", bukan "co.id").
     *
     * Nilai *_conv dinyatakan dalam ribuan rupiah — sama seperti label
     * "IDR (in 1000's)" di panel reseller — jadi dikalikan 1.000.
     *
     * @return array{success: bool, message: string, prices: array<string, array>, raw: mixed}
     */
    public function listPrices(): array
    {
        $account = $this->call('get', '/account/prices', [], self::PRICE_TIMEOUT);

        if (! $account['success'] || ! is_array($account['raw'])) {
            return [
                'success' => false,
                'message' => 'Gagal mengambil harga modal: ' . $account['message'],
                'prices' => [],
                'raw' => $account['raw'],
            ];
        }

        // Peta kode internal → ekstensi asli. Kalau endpoint ini gagal,
        // proses tetap lanjut memakai tld_key sebagai cadangan.
        $labels = $this->fetchTldLabels();

        $prices = [];

        foreach ($account['raw'] as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $tldName = $row['tld_name'] ?? null;
            $tldKey  = $row['tld_key'] ?? (is_string($key) ? $key : null);

            $ext = ($tldName && isset($labels[$tldName]['extension']))
                ? $labels[$tldName]['extension']
                : ($tldKey ? $this->normalizeExtension($tldKey) : null);

            if (! $ext) {
                continue;
            }

            $register = $this->convPrice($row, 'price_new_conv', 'price_new');
            $renew    = $this->convPrice($row, 'price_renew_conv', 'price_renew');
            $transfer = $this->convPrice($row, 'price_transfer_conv', 'price_transfer');

            if ($register === null) {
                continue;
            }

            $prices[$ext] = [
                'register' => $register,
                'renew'    => $renew ?? $register,
                'transfer' => $transfer ?? $register,
                'currency' => $row['currency'] ?? 'IDR',
                // "Sell" berarti TLD ini sudah kamu aktifkan untuk dijual
                // di panel Liqu.id — dipakai untuk mencentang otomatis.
                'sellable' => ($labels[$tldName]['pref'] ?? null) === 'Sell',
            ];
        }

        if (empty($prices)) {
            Log::warning('Liqu.id: /account/prices terbaca tapi tidak ada harga yang bisa dipetakan.', [
                'registrar_id' => $this->registrar->id,
                'sample' => is_array($account['raw']) ? reset($account['raw']) : null,
            ]);

            return [
                'success' => false,
                'message' => 'Respons harga terbaca tapi tidak ada ekstensi yang cocok. Jalankan "php artisan lumora:liquid-prices" untuk memeriksa.',
                'prices' => [],
                'raw' => $account['raw'],
            ];
        }

        return ['success' => true, 'message' => 'OK', 'prices' => $prices, 'raw' => $account['raw']];
    }

    /**
     * Ambil peta kode internal TLD → ekstensi & status jual.
     *
     * @return array<string, array{extension: string, pref: ?string}>
     */
    protected function fetchTldLabels(): array
    {
        $response = $this->call('get', '/resellers/prices', [], self::PRICE_TIMEOUT);

        if (! $response['success'] || ! is_array($response['raw'])) {
            return [];
        }

        $map = [];

        foreach ($response['raw'] as $tldName => $rows) {
            // Tiap entri berupa array slab harga; ambil slab pertama.
            $row = is_array($rows) ? (is_array($rows[0] ?? null) ? $rows[0] : $rows) : null;

            if (! $row || empty($row['tld_label'])) {
                continue;
            }

            $map[$tldName] = [
                'extension' => $this->normalizeExtension((string) $row['tld_label']),
                'pref'      => $row['tld_pref'] ?? null,
            ];
        }

        return $map;
    }

    /**
     * Baca harga dalam rupiah. Field *_conv dinyatakan dalam ribuan,
     * jadi dikalikan 1.000 agar jadi rupiah penuh.
     */
    protected function convPrice(array $row, string $convField, string $rawField): ?float
    {
        if (isset($row[$convField]) && is_numeric($row[$convField])) {
            return round((float) $row[$convField] * 1000, 2);
        }

        // Cadangan: kalau hanya ada harga USD, tidak bisa dipakai langsung
        // karena kurs konversinya milik Liqu.id — lebih baik dikosongkan
        // daripada menyimpan angka yang salah.
        return null;
    }

    /**
     * Cari nilai harga dari beberapa kemungkinan nama field.
     * Struktur harga Liqu.id bisa datar (`register`) atau bersarang
     * (`register` => [`1` => 150000]) untuk harga per tahun.
     */
    protected function pickPrice(array $row, array $candidates): ?float
    {
        foreach ($candidates as $field) {
            if (! array_key_exists($field, $row)) {
                continue;
            }

            $value = $row[$field];

            // Harga per durasi — ambil tahun ke-1.
            if (is_array($value)) {
                $value = $value[1] ?? $value['1'] ?? reset($value);
            }

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    protected function normalizeExtension(string $ext): string
    {
        return '.' . ltrim(strtolower(trim($ext)), '.');
    }

    /**
     * Liqu.id mengirim durasi sebagai teks "10 years" — ambil angkanya.
     */
    protected function parseYears(mixed $value, int $default): int
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return $default;
        }

        preg_match('/\d+/', (string) $value, $m);

        $years = isset($m[0]) ? (int) $m[0] : $default;

        // Kolom di database bertipe tinyint dan form membatasi 1–10.
        return max(1, min($years, 10));
    }

    // ─────────────────────────────────────────────────────────────
    // Helper internal
    // ─────────────────────────────────────────────────────────────

    /**
     * Cari customer berdasarkan email; buat baru kalau belum ada.
     *
     * @return array{success: bool, customer_id: int|string|null, message: string, raw: mixed}
     */
    protected function resolveCustomerId(array $c): array
    {
        $existing = $this->call('get', '/customers', ['email' => $c['email'], 'limit' => 1]);

        if ($existing['success']) {
            $found = $this->firstRow($existing['raw']);

            if ($found && ! empty($found['customer_id'])) {
                return ['success' => true, 'customer_id' => $found['customer_id'], 'message' => 'OK', 'raw' => $existing['raw']];
            }
        }

        [$ccNo, $telNo] = $this->splitPhone($c['phone']);

        $created = $this->call('post', '/customers', [
            'email'          => $c['email'],
            'name'           => trim($c['first_name'] . ' ' . $c['last_name']),
            'password'       => $this->generatePassword(),
            'company'        => $c['company'] ?? trim($c['first_name'] . ' ' . $c['last_name']),
            'address_line_1' => $c['address'],
            'city'           => $c['city'],
            'state'          => $c['state'],
            'country_code'   => strtoupper($c['country']),
            'zipcode'        => $c['postal_code'],
            'tel_cc_no'      => $ccNo,
            'tel_no'         => $telNo,
        ]);

        $row = $this->firstRow($created['raw']);

        return [
            'success'     => $created['success'] && ! empty($row['customer_id']),
            'customer_id' => $row['customer_id'] ?? null,
            'message'     => $created['message'],
            'raw'         => $created['raw'],
        ];
    }

    /**
     * POST /customers/{customer_id}/contacts
     *
     * @return array{success: bool, contact_id: int|string|null, message: string, raw: mixed}
     */
    /**
     * TLD yang butuh data kelayakan tambahan (eligibility) sebelum bisa
     * didaftarkan — dikonfirmasi dari spesifikasi resmi Liqu.id, endpoint
     * PUT /customers/{id}/contacts/{id}/extra. Domain untuk TLD ini
     * SENGAJA tidak didaftarkan otomatis begitu invoice lunas — admin
     * perlu isi data kelayakannya dulu (lihat ProvisioningService).
     *
     * Format nilai yang benar per TLD TIDAK ditebak di sini — cuma .us
     * dan .asia yang formatnya dikonfirmasi dari dokumentasi resmi;
     * sisanya (.ca, .coop, .es, .jobs, .nl, .pro, .ru) perlu dicek
     * manual di dashboard Liqu.id atau tanya support mereka sebelum
     * diisi, supaya tidak asal tebak untuk domain klien sungguhan.
     */
    public const ELIGIBILITY_REQUIRED_TLDS = ['asia', 'ca', 'coop', 'es', 'jobs', 'nl', 'pro', 'ru', 'us'];

    public const ELIGIBILITY_EXAMPLES = [
        'us'   => 'us_purpose=business&us_category=citizen',
        'asia' => 'asia_contact_id=0',
    ];

    /**
     * PUT /customers/{customer_id}/contacts/{contact_id}/extra — wajib
     * dipanggil untuk 9 TLD di ELIGIBILITY_REQUIRED_TLDS sebelum domain
     * benar-benar bisa didaftarkan, kalau tidak permintaan registrasi
     * akan ditolak registry (bukan Liqu.id-nya, tapi pengelola TLD
     * aslinya).
     */
    public function updateContactEligibility(int|string $customerId, int|string $contactId, string $eligibilityCriteria, string $extra): array
    {
        return $this->call('put', "/customers/{$customerId}/contacts/{$contactId}/extra", [
            'eligibility_criteria' => $eligibilityCriteria,
            'extra' => $extra,
        ]);
    }

    protected function createContact(int|string $customerId, array $c): array
    {
        [$ccNo, $telNo] = $this->splitPhone($c['phone']);

        $created = $this->call('post', "/customers/{$customerId}/contacts", [
            'name'           => trim($c['first_name'] . ' ' . $c['last_name']),
            'company'        => $c['company'] ?? trim($c['first_name'] . ' ' . $c['last_name']),
            'email'          => $c['email'],
            'address_line_1' => $c['address'],
            'city'           => $c['city'],
            'state'          => $c['state'],
            'country_code'   => strtoupper($c['country']),
            'zipcode'        => $c['postal_code'],
            'tel_cc_no'      => $ccNo,
            'tel_no'         => $telNo,
        ]);

        $row = $this->firstRow($created['raw']);

        return [
            'success'    => $created['success'] && ! empty($row['contact_id']),
            'contact_id' => $row['contact_id'] ?? null,
            'message'    => $created['message'],
            'raw'        => $created['raw'],
        ];
    }

    /**
     * GET /domains/details-by-name?domain_name=contoh.com
     *
     * @return array{success: bool, domain_id: int|string|null, message: string, raw: mixed}
     */
    protected function findDomainId(string $domain): array
    {
        $response = $this->call('get', '/domains/details-by-name', ['domain_name' => $domain]);

        $row = $this->firstRow($response['raw']);

        if (! $response['success'] || empty($row['domain_id'])) {
            return [
                'success' => false,
                'domain_id' => null,
                'message' => "Domain {$domain} tidak ditemukan di akun Liqu.id ini. " . $response['message'],
                'raw' => $response['raw'],
            ];
        }

        return ['success' => true, 'domain_id' => $row['domain_id'], 'message' => 'OK', 'raw' => $response['raw']];
    }

    /**
     * Ubah respons availability jadi map [domain => tersedia?].
     *
     * Liqu.id bisa mengembalikan beberapa bentuk, jadi ditangani fleksibel.
     */
    protected function parseAvailability(mixed $raw, array $requested): array
    {
        $results = [];

        if (! is_array($raw)) {
            return $results;
        }

        // Bentuk 1: map { "contoh.com": { "status": "available" } }
        foreach ($raw as $key => $value) {
            if (is_string($key) && str_contains($key, '.') && is_array($value)) {
                $results[$key] = $this->isAvailableFlag($value);
            }
        }

        if ($results) {
            return $results;
        }

        // Bentuk 2: array of objects [ { "domain": "contoh.com", "status": "available" } ]
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = $item['domain'] ?? $item['domain_name'] ?? null;

            if ($name) {
                $results[$name] = $this->isAvailableFlag($item);
            }
        }

        return $results;
    }

    /**
     * Baca penanda ketersediaan dari berbagai kemungkinan nama field.
     */
    protected function isAvailableFlag(array $item): bool
    {
        if (isset($item['available'])) {
            return filter_var($item['available'], FILTER_VALIDATE_BOOLEAN);
        }

        $status = strtolower((string) ($item['status'] ?? ''));

        return in_array($status, ['available', 'true', 'yes'], true);
    }

    /**
     * Ambil baris pertama dari respons — Liqu.id mengembalikan array of
     * objects untuk endpoint list, tapi object tunggal untuk create.
     */
    protected function firstRow(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        // Object tunggal (punya key non-numerik)
        if (! array_is_list($raw)) {
            return $raw;
        }

        $first = $raw[0] ?? null;

        return is_array($first) ? $first : null;
    }

    /**
     * Pisahkan "+62.81234567890" atau "+6281234567890" jadi [kode negara, nomor].
     *
     * @return array{0: string, 1: string}
     */
    protected function splitPhone(string $phone): array
    {
        $phone = trim($phone);

        // Format Namecheap-style "+62.81234567890"
        if (str_contains($phone, '.')) {
            [$cc, $no] = explode('.', ltrim($phone, '+'), 2);

            return [preg_replace('/\D/', '', $cc), preg_replace('/\D/', '', $no)];
        }

        $digits = preg_replace('/\D/', '', $phone);

        // Nomor Indonesia: 62xxx atau 08xxx
        if (str_starts_with($digits, '62')) {
            return ['62', substr($digits, 2)];
        }

        if (str_starts_with($digits, '0')) {
            return ['62', ltrim($digits, '0')];
        }

        return ['62', $digits];
    }

    protected function generatePassword(): string
    {
        // Liqu.id membatasi MAKSIMAL 15 karakter -- dikonfirmasi dari
        // error nyata: "Password cannot be longer than 15 characters".
        // Versi sebelumnya menghasilkan 20 karakter (16 acak + 4 karakter
        // tambahan manual), yang justru DITOLAK Liqu.id dan bikin SEMUA
        // pendaftaran domain gagal diam-diam.
        //
        // Str::password() bawaan Laravel sudah menjamin campuran huruf
        // besar/kecil, angka, dan simbol secara default -- tidak perlu
        // ditambah manual lagi seperti sebelumnya.
        return \Illuminate\Support\Str::password(14);
    }

    // ─────────────────────────────────────────────────────────────
    // Riwayat Transaksi Akun
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /account/transactions — riwayat transaksi (potongan saldo
     * tiap kali daftar/perpanjang domain, dll). Berguna untuk
     * rekonsiliasi keuangan tanpa perlu login terpisah ke Liqu.id.
     */
    public function getAccountTransactions(int $limit = 20, int $page = 1): array
    {
        $result = $this->call('get', '/account/transactions', [
            'limit' => min($limit, 100),
            'page_no' => $page,
        ]);

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'], 'transactions' => [], 'raw' => $result['raw']];
        }

        $rows = is_array($result['raw']) && array_is_list($result['raw']) ? $result['raw'] : ($result['raw']['transactions'] ?? []);

        return [
            'success' => true,
            'message' => 'OK',
            'transactions' => array_map(fn ($row) => [
                'id'          => $row['transaction_id'] ?? $row['id'] ?? null,
                'type'        => $row['transaction_type'] ?? '—',
                'description' => $row['description'] ?? '',
                'amount'      => (float) ($row['amount'] ?? 0),
                'date'        => $row['creation_date'] ?? $row['date'] ?? null,
            ], (array) $rows),
            'raw' => $result['raw'],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Domain Forwarding
    // ─────────────────────────────────────────────────────────────

    public function getDomainForwarding(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'forward_to' => null, 'raw' => $lookup['raw']];
        }

        $result = $this->call('get', "/domains/{$lookup['domain_id']}/domain_forwarding");

        if (! $result['success'] && (str_contains(strtolower($result['message']), 'doesn\'t exist') || str_contains(strtolower($result['message']), 'does not exist'))) {
            return ['success' => true, 'message' => 'OK', 'forward_to' => null, 'raw' => $result['raw']];
        }

        $raw = $result['raw'];

        // Bisa berupa string URL polos (pola endpoint per-domain Liqu.id
        // yang lain) atau objek — dua-duanya ditangani.
        if (is_string($raw)) {
            $forwardTo = trim($raw, " \t\n\r\0\x0B\"") ?: null;
        } else {
            $row = $this->firstRow($raw);
            $forwardTo = $row['forward_to'] ?? null;
        }

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'forward_to' => $forwardTo,
            'raw' => $result['raw'],
        ];
    }

    /**
     * Aktifkan forwarding (isi $forwardTo) atau matikan (kirim string
     * kosong — begitu cara resminya menonaktifkan, tidak ada endpoint
     * DELETE terpisah untuk fitur ini).
     */
    public function updateDomainForwarding(string $domain, string $forwardTo): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('put', "/domains/{$lookup['domain_id']}/domain_forwarding", [
            'forward_to' => $forwardTo,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Email Forwarding
    // ─────────────────────────────────────────────────────────────

    public function listEmailForwarding(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'forwards' => [], 'raw' => $lookup['raw']];
        }

        $result = $this->call('get', "/domains/{$lookup['domain_id']}/email_forwarding");

        // Liqu.id mengembalikan error "doesn't exist" kalau memang belum
        // ada satu pun aturan forward yang dibuat — itu bukan kegagalan,
        // itu cara mereka bilang "daftar kosong". Diperlakukan sebagai
        // daftar kosong, bukan ditampilkan sebagai error ke klien.
        if (! $result['success']) {
            if (str_contains(strtolower($result['message']), 'doesn\'t exist') || str_contains(strtolower($result['message']), 'does not exist')) {
                return ['success' => true, 'message' => 'OK', 'forwards' => [], 'raw' => $result['raw']];
            }

            return ['success' => false, 'message' => $result['message'], 'forwards' => [], 'raw' => $result['raw']];
        }

        $rows = is_array($result['raw']) && array_is_list($result['raw']) ? $result['raw'] : [];

        return [
            'success' => true,
            'message' => 'OK',
            'forwards' => array_map(fn ($row) => [
                'email' => $row['email'] ?? '',
                'forward_to' => $row['forward_to'] ?? '',
            ], $rows),
            'raw' => $result['raw'],
        ];
    }

    /**
     * Maksimal 5 tujuan per alamat email — batasan resmi dari Liqu.id,
     * bukan yang kita buat sendiri.
     */
    public function addEmailForwarding(string $domain, string $email, array $forwardTo): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('post', "/domains/{$lookup['domain_id']}/email_forwarding", [
            'email' => $email,
            'forward_to' => implode(',', array_slice($forwardTo, 0, 5)),
        ]);
    }

    public function deleteEmailForwarding(string $domain, string $email): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('delete', "/domains/{$lookup['domain_id']}/email_forwarding/" . rawurlencode($email));
    }

    // ─────────────────────────────────────────────────────────────
    // Theft Protection
    // ─────────────────────────────────────────────────────────────

    /**
     * Beda dari Privacy Protection (yang menyembunyikan data WHOIS) —
     * ini proteksi tambahan supaya domain tidak bisa "dicuri" lewat
     * perubahan data registrant tanpa verifikasi ekstra.
     */
    public function getTheftProtection(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'enabled' => null, 'raw' => $lookup['raw']];
        }

        $result = $this->call('get', "/domains/{$lookup['domain_id']}/theft_protection");

        // Sama seperti getDomainLockStatus -- endpoint ini mengembalikan
        // boolean POLOS ("true"/"false"), bukan objek {"enabled": true}.
        $enabled = filter_var($result['raw'], FILTER_VALIDATE_BOOLEAN);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'enabled' => $enabled,
            'raw' => $result['raw'],
        ];
    }

    public function enableTheftProtection(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('put', "/domains/{$lookup['domain_id']}/theft_protection");
    }

    public function disableTheftProtection(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('delete', "/domains/{$lookup['domain_id']}/theft_protection");
    }

    // ─────────────────────────────────────────────────────────────
    // DNSSEC — teknis lanjutan, dibiarkan backend-only
    // ─────────────────────────────────────────────────────────────

    /**
     * Menambah DS record butuh 4 nilai teknis (keytag, algorithm,
     * digesttype, digest) yang dihasilkan software DNS klien sendiri
     * (mis. BIND, PowerDNS) — bukan sesuatu yang bisa diisi klien awam
     * lewat form biasa. Disediakan di sini untuk dipakai admin/API,
     * sengaja TIDAK dibuatkan halaman klien.
     */
    public function addDnssecRecord(string $domain, int $keytag, int $algorithm, int $digestType, string $digest): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('post', "/domains/{$lookup['domain_id']}/dnssec", [
            'keytag' => $keytag,
            'algorithm' => $algorithm,
            'digesttype' => $digestType,
            'digest' => $digest,
        ]);
    }

    public function listDnssecRecords(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'records' => [], 'raw' => $lookup['raw']];
        }

        $result = $this->call('get', "/domains/{$lookup['domain_id']}/dnssec");
        $rows = is_array($result['raw']) && array_is_list($result['raw']) ? $result['raw'] : [];

        return ['success' => $result['success'], 'message' => $result['message'], 'records' => $rows, 'raw' => $result['raw']];
    }

    // ─────────────────────────────────────────────────────────────
    // Beli Privacy Protection (jalur terpisah dari enable biasa)
    // ─────────────────────────────────────────────────────────────

    /**
     * Sebagian TLD tidak mengizinkan Privacy Protection diaktifkan
     * gratis lewat enablePrivacyProtection() — harus lewat jalur BELI
     * eksplisit ini. Dipakai sebagai jalan kedua kalau enable() gagal.
     */
    public function buyPrivacyProtection(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('post', "/domains/{$lookup['domain_id']}/privacy_protection/buy", [
            'invoice_option' => 'no_invoice',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Restore Domain (masa tenggang setelah kedaluwarsa)
    // ─────────────────────────────────────────────────────────────

    /**
     * Domain yang baru saja kedaluwarsa biasanya masih bisa dipulihkan
     * lewat "masa tenggang" (redemption period) registri, dengan biaya
     * tambahan — sebelum benar-benar dilepas dan bisa didaftarkan orang
     * lain. Jendela waktunya beda-beda tiap TLD, biasanya sekitar 30 hari.
     */
    public function restoreDomain(string $domain): array
    {
        $lookup = $this->findDomainId($domain);

        if (! $lookup['success']) {
            return ['success' => false, 'message' => $lookup['message'], 'raw' => $lookup['raw']];
        }

        return $this->call('post', "/domains/{$lookup['domain_id']}/restore", [
            'invoice_option' => 'no_invoice',
        ]);
    }

    /**
     * Panggil API Liqu.id dengan HTTP Basic Auth (Reseller ID : API Key).
     *
     * @return array{success: bool, message: string, raw: mixed}
     */
    protected function call(string $method, string $endpoint, array $params = [], ?int $timeout = null): array
    {
        try {
            $client = $this->client($timeout);

            $response = match ($method) {
                'post'  => $client->asForm()->post($endpoint, $params),
                'put'   => $client->asForm()->put($endpoint, $params),
                'delete' => $client->delete($endpoint, $params),
                default => $client->get($endpoint, $params),
            };

            $body = $response->json();

            if ($response->status() === 401) {
                return [
                    'success' => false,
                    'message' => 'Autentikasi ditolak. Cek Reseller ID dan API Key.',
                    'raw' => $body,
                ];
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->extractError($body) ?? "Liqu.id mengembalikan HTTP {$response->status()}.",
                    'raw' => $body ?? $response->body(),
                ];
            }

            return ['success' => true, 'message' => 'OK', 'raw' => $body];
        } catch (Throwable $e) {
            Log::warning("Liqu.id API [{$method} {$endpoint}] gagal: " . $e->getMessage(), [
                'registrar_id' => $this->registrar->id,
            ]);

            return [
                'success' => false,
                'message' => 'Tidak bisa terhubung ke Liqu.id: ' . $e->getMessage(),
                'raw' => null,
            ];
        }
    }

    /**
     * Format error Liqu.id: { "type": ..., "message": ..., "code": ... }
     */
    protected function extractError(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $message = $body['message'] ?? null;
        $code = $body['code'] ?? null;

        if ($message && $code) {
            return "{$message} ({$code})";
        }

        return $message;
    }

    protected function client(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withBasicAuth(
                (string) $this->registrar->api_username,   // Reseller ID
                (string) $this->registrar->api_key         // API Key
            )
            ->acceptJson()
            ->timeout($timeout ?? 25);
    }

    protected function baseUrl(): string
    {
        if (filled($this->registrar->api_url)) {
            return rtrim($this->registrar->api_url, '/');
        }

        return $this->registrar->sandbox ? self::DEFAULT_DEMO_URL : self::DEFAULT_LIVE_URL;
    }
}
