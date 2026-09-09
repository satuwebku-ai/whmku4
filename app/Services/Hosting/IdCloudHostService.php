<?php

namespace App\Services\Hosting;

use App\Models\Server;
use App\Services\Hosting\Contracts\HostingPanelInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Integrasi IDCloudHost -- BEDA dari cPanel/DirectAdmin/Plesk: bukan
 * "panel" di server yang sudah ada, tapi menyediakan Virtual
 * Machine/VPS BARU sepenuhnya lewat API on-demand (create/delete VM
 * itu sendiri, bukan sekadar akun di dalam server).
 *
 * Field tabel servers dipakai ulang secara kreatif (tanpa kolom baru):
 *   - hostname     -> slug lokasi datacenter (jkt01/jkt02/jkt03/sgp01),
 *                     kosongkan untuk pakai lokasi default akun.
 *   - api_username -> billing_account_id (opsional -- boleh kosong
 *                     kalau API token sudah dibatasi ke satu akun billing)
 *   - api_token    -> API key dari dashboard IDCloudHost
 *
 * Field "package" (dari HostingAccount/Product->panel_package) berisi
 * JSON, BUKAN nama paket seperti WHM -- karena IDCloudHost tidak
 * punya konsep "paket" baku, tiap VM ditentukan vcpu/ram/disk/OS
 * masing-masing secara eksplisit. Contoh isi panel_package:
 *   {"vcpu":2,"ram":2048,"disk":40,"os_name":"ubuntu","os_version":"22.04"}
 *
 * Dokumentasi API: https://api.idcloudhost.com/
 */
class IdCloudHostService implements HostingPanelInterface
{
    public function __construct(protected Server $server)
    {
    }

    protected function client(int $timeout = 30)
    {
        return Http::withHeaders(['apikey' => $this->server->api_token])
            ->baseUrl($this->baseUrl())
            ->timeout($timeout);
    }

    /**
     * Semua endpoint resource (VM, billing, IP) itu location-specific --
     * slug lokasi disisipkan tepat setelah nomor versi. Dikosongkan
     * untuk memakai lokasi default akun IDCloudHost.
     */
    protected function baseUrl(): string
    {
        $slug = trim((string) $this->server->hostname);

        return $slug !== ''
            ? "https://api.idcloudhost.com/v1/{$slug}"
            : 'https://api.idcloudhost.com/v1';
    }

    /**
     * Billing Account ID untuk dikirim ke API.
     *
     * HANYA dikirim kalau berupa angka -- API menolak mentah-mentah
     * nilai non-angka dengan "Invalid whole number provided", dan itu
     * menggagalkan SELURUH pembuatan VM. Kolom ini gampang keliru diisi
     * nama/judul akun, jadi kalau isinya bukan angka lebih baik
     * dikosongkan saja: provider akan memakai billing account default
     * milik API token tersebut.
     */
    protected function billingAccountId(): ?int
    {
        $id = trim((string) $this->server->api_username);

        return ctype_digit($id) ? (int) $id : null;
    }

    /**
     * Uraikan panel_package (JSON) jadi array spesifikasi VM, dengan
     * nilai default yang aman kalau ada field yang tidak diisi.
     */
    protected function decodePackage(string $package): array
    {
        $spec = json_decode($package, true) ?: [];

        return [
            'vcpu'           => (int) ($spec['vcpu'] ?? 1),
            'ram'            => (int) ($spec['ram'] ?? 1024),
            'disk'           => (int) ($spec['disk'] ?? 20),
            'os_name'        => $spec['os_name'] ?? 'ubuntu',
            'os_version'     => $spec['os_version'] ?? '22.04',
            // Dipakai kartu harga per komponen (ChargeHourlyUsage) --
            // bukan cuma untuk provisioning API, tapi juga dasar
            // perhitungan tagihan per jam.
            'backup_enabled' => (bool) ($spec['backup_enabled'] ?? false),
            'snapshot_gb'    => (float) ($spec['snapshot_gb'] ?? 0),
            'pool_uuid'      => $spec['pool_uuid'] ?? null,
            'location'       => $spec['location'] ?? null,
        ];
    }

    /**
     * Ambil alasan penolakan yang SEBENARNYA dari respons provider.
     *
     * IDCloudHost mengirim detail error dalam bentuk berbeda-beda
     * (kadang {"errors":{"Error":"..."}}, kadang per-field, kadang
     * teks biasa). Tanpa penanganan ini, admin cuma melihat
     * "HTTP 400" yang tidak memberi petunjuk apa pun soal field mana
     * yang bermasalah. Respons lengkapnya juga dicatat ke log.
     */
    protected function extractError($body, int $status, string $method, string $path, array $payload = []): string
    {
        Log::warning("IDCloudHost menolak [{$method} {$path}]", [
            'server_id' => $this->server->id,
            'status'    => $status,
            'payload'   => $payload,
            'response'  => $body,
        ]);

        if (is_array($body)) {
            if (! empty($body['errors']['Error'])) {
                return (string) $body['errors']['Error'];
            }

            if (! empty($body['message']) && is_scalar($body['message'])) {
                return (string) $body['message'];
            }

            if (! empty($body['errors']) && is_array($body['errors'])) {
                $parts = [];
                foreach ($body['errors'] as $field => $detail) {
                    $parts[] = is_scalar($detail)
                        ? "{$field}: {$detail}"
                        : "{$field}: " . json_encode($detail, JSON_UNESCAPED_SLASHES);
                }

                return implode(' | ', $parts);
            }

            return "Ditolak (HTTP {$status}): " . mb_strimwidth(json_encode($body, JSON_UNESCAPED_SLASHES), 0, 400, '…');
        }

        return "Permintaan ditolak (HTTP {$status}) tanpa keterangan dari provider.";
    }

    protected function call(string $method, string $path, array $data = [], int $timeout = 30): array
    {
        try {
            $response = $this->client($timeout)->{$method}($path, $data);
            $body = $response->json();

            if ($response->successful()) {
                return ['success' => true, 'message' => 'OK', 'raw' => $body];
            }

            $message = $this->extractError($body, $response->status(), $method, $path, $data);

            return ['success' => false, 'message' => $message, 'raw' => $body];
        } catch (Throwable $e) {
            Log::warning("IDCloudHost API [{$method} {$path}] gagal: " . $e->getMessage(), ['server_id' => $this->server->id]);

            return ['success' => false, 'message' => 'Tidak bisa terhubung ke IDCloudHost: ' . $e->getMessage(), 'raw' => null];
        }
    }

    // ── HostingPanelInterface ──────────────────────────────────────

    /**
     * "Buat akun" untuk VM = buat VM baru sepenuhnya.
     *
     * $params['username']/['password'] dipakai sebagai login VM.
     * $params['domain'] dipakai sebagai nama/hostname VM (VM tidak
     * punya "domain" sungguhan seperti akun hosting -- cuma label).
     * $params['package'] berisi JSON spesifikasi (lihat decodePackage()).
     */
    public function createAccount(array $params): array
    {
        $spec = $this->decodePackage($params['package'] ?? '{}');

        // Nama VM di IDCloudHost cuma boleh huruf/angka/strip, dan tidak
        // boleh diawali/diakhiri strip -- domain klien sering mengandung
        // titik, jadi disaring dulu supaya tidak ditolak API.
        $vmName = preg_replace('/[^a-zA-Z0-9-]/', '-', $params['domain'] ?? 'vm-' . uniqid());
        $vmName = trim($vmName, '-') ?: 'vm-' . uniqid();

        // PENTING: pembuatan VM bisa memakan waktu lebih dari timeout
        // HTTP, sehingga permintaan bisa "timeout" di sisi kita padahal
        // VM-nya SUDAH TERBENTUK di provider. Kalau lalu dicoba ulang
        // tanpa pengecekan, akan muncul VM GANDA yang dua-duanya
        // menagih biaya. Jadi sebelum membuat, dicek dulu apakah sudah
        // ada VM bernama sama -- kalau ada, VM itu yang dipakai.
        $existing = $this->findVmByName($vmName);

        if ($existing) {
            return [
                'success'  => true,
                'message'  => 'VM dengan nama ini sudah ada di provider — dipakai yang sudah ada, tidak membuat baru.',
                'raw'      => $existing,
                'username' => $existing['uuid'] ?? null,
                'ip'       => $existing['public_ipv4'] ?? $existing['private_ipv4'] ?? null,
            ];
        }

        $payload = array_filter([
            'name'                 => $vmName,
            'os_name'              => $spec['os_name'],
            'os_version'           => $spec['os_version'],
            'disks'                => $spec['disk'],
            'vcpu'                 => $spec['vcpu'],
            'ram'                  => $spec['ram'],
            'username'             => $params['username'] ?? null,
            'password'             => $params['password'] ?? null,
            'billing_account_id'   => $this->billingAccountId(),
            'designated_pool_uuid' => $spec['pool_uuid'] ?? null,
        ], fn ($v) => $v !== null);

        // Timeout diperpanjang jauh -- provider butuh waktu menyiapkan
        // disk & menyalakan mesin, 30 detik sering tidak cukup.
        $result = $this->call('post', '/user-resource/vm', $payload, 180);

        // Kalau tetap timeout, cek sekali lagi: mungkin VM-nya berhasil
        // dibuat tepat setelah kita menyerah menunggu.
        if (! $result['success'] && str_contains(strtolower($result['message']), 'timed out')) {
            sleep(5);
            $late = $this->findVmByName($vmName);

            if ($late) {
                return [
                    'success'  => true,
                    'message'  => 'VM berhasil dibuat (jawaban provider terlambat, tapi VM-nya sudah ada).',
                    'raw'      => $late,
                    'username' => $late['uuid'] ?? null,
                    'ip'       => $late['public_ipv4'] ?? $late['private_ipv4'] ?? null,
                ];
            }
        }

        if (! $result['success']) {
            return $result;
        }

        // uuid VM disimpan sebagai "username" di HostingAccount (lewat
        // kolom yang sama dipakai cPanel) -- itu satu-satunya pengenal
        // yang dibutuhkan semua operasi VM berikutnya (start/stop/dst).
        return [
            'success' => true,
            'message' => 'VM berhasil dibuat.',
            'raw'      => $result['raw'],
            'username' => $result['raw']['uuid'] ?? null,
            'ip'       => $result['raw']['public_ipv4'] ?? $result['raw']['public_ipv6'] ?? null,
        ];
    }

    /**
     * Cari VM berdasarkan nama di akun ini. Dipakai sebagai pengaman
     * anti-duplikat: pembuatan VM yang "timeout" di sisi kita belum
     * tentu gagal di sisi provider.
     *
     * Sengaja memakai timeout pendek & gagal diam-diam (return null)
     * -- ini cuma pengecekan pendukung, tidak boleh ikut menggagalkan
     * proses utama kalau API-nya sedang lambat.
     */
    protected function findVmByName(string $name): ?array
    {
        try {
            $result = $this->call('get', '/user-resource/vm/list', [], 20);

            if (! $result['success'] || ! is_array($result['raw'])) {
                return null;
            }

            foreach ($result['raw'] as $vm) {
                if (($vm['name'] ?? null) === $name) {
                    return $vm;
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * "Suspend" untuk VM = matikan (stop). Beda dari cPanel yang punya
     * status suspend tersendiri -- IDCloudHost tidak punya konsep itu,
     * jadi paling dekat adalah menghentikan VM (data tetap ada di disk,
     * cuma tidak berjalan / tidak menagih compute, tapi tetap menagih
     * storage).
     */
    public function suspendAccount(string $username, ?string $reason = null): array
    {
        return $this->call('post', '/user-resource/vm/stop', ['uuid' => $username]);
    }

    public function unsuspendAccount(string $username): array
    {
        return $this->call('post', '/user-resource/vm/start', ['uuid' => $username]);
    }

    public function terminateAccount(string $username): array
    {
        return $this->call('delete', '/user-resource/vm', ['uuid' => $username]);
    }

    /**
     * "Ganti paket" = ubah vcpu/ram VM. PENTING: API IDCloudHost cuma
     * mengizinkan ini saat VM berstatus stopped -- kalau masih running,
     * panggilan ini akan ditolak. Disk TIDAK bisa diperkecil/diperbesar
     * lewat endpoint ini (perlu Add Disk / Modify Disk terpisah).
     */
    /**
     * Matikan paksa -- setara mencabut listrik. Dipakai kalau VM tidak
     * merespons perintah matikan normal (OS hang). BERISIKO merusak
     * data yang belum tersimpan ke disk, jadi hanya untuk keadaan
     * darurat, bukan cara mematikan sehari-hari.
     */
    public function forceStop(string $uuid): array
    {
        return $this->call('post', '/user-resource/vm/stop', ['uuid' => $uuid, 'force' => true], 60);
    }

    public function changePackage(string $username, string $package): array
    {
        $spec = $this->decodePackage($package);

        return $this->call('patch', '/user-resource/vm', [
            'uuid' => $username,
            'vcpu' => $spec['vcpu'],
            'ram'  => $spec['ram'],
        ]);
    }

    public function testConnection(): array
    {
        return $this->call('get', '/user-resource/user');
    }

    // ── Operasi khusus VM (di luar interface bersama) ──────────────

    public function getVmInfo(string $uuid): array
    {
        return $this->call('get', '/user-resource/vm', ['uuid' => $uuid]);
    }

    /**
     * Dipakai fitur "Ganti Password" di dashboard klien -- sama seperti
     * changePanelPassword untuk cPanel. VM harus dalam keadaan running.
     */
    public function changePassword(string $uuid, string $username, string $password): array
    {
        return $this->call('patch', '/user-resource/vm/user', [
            'uuid'     => $uuid,
            'username' => $username,
            'password' => $password,
        ]);
    }

    /**
     * Instal ulang VM dari awal -- SEMUA data di disk utama hilang.
     * Dipakai kalau klien/admin ingin mulai bersih tanpa membuat VM baru
     * (IP & spesifikasi tetap sama).
     */
    public function reinstall(string $uuid, ?string $osName = null, ?string $osVersion = null): array
    {
        return $this->call('post', '/user-resource/vm/reinstall', array_filter([
            'uuid'       => $uuid,
            'os_name'    => $osName,
            'os_version' => $osVersion,
        ]));
    }

    /**
     * Daftar OS yang tersedia -- dipakai admin saat menyiapkan produk
     * VPS baru (pilihan os_name/os_version yang valid untuk dijual).
     */
    public function listOsImages(): array
    {
        return $this->call('get', '/config/vm_images/plain_os');
    }

    /**
     * Daftar semua VM di lokasi/akun ini -- dipakai halaman Diagnosa
     * khusus IDCloudHost (pengganti "daftar paket & akun" ala cPanel,
     * yang tidak relevan untuk provider ini).
     */
    public function listVms(): array
    {
        return $this->call('get', '/user-resource/vm/list');
    }

    // ── Endpoint diagnosa (semua GET, hanya membaca) ───────────────
    // Semua path di bawah diambil langsung dari dokumentasi resmi
    // https://api.idcloudhost.com/ -- lihat catatan khusus di
    // creditCandidates() untuk yang belum terkonfirmasi.

    /** Info akun/user pemilik API key ini. */
    public function getUserInfo(): array
    {
        return $this->callGlobal('get', '/user-resource/user');
    }

    /** Daftar lokasi datacenter + mana yang default. */
    public function listLocations(): array
    {
        return $this->callGlobal('get', '/config/locations');
    }

    /** Kelas server / resource pool (mis. General, Performance). */
    public function listHostPools(): array
    {
        return $this->call('get', '/user-resource/host_pool/list');
    }

    /**
     * Batasan parameter VM: min/max vCPU, RAM, disk, dan daftar OS
     * yang valid -- ini "aturan main" yang HARUS dipatuhi saat menyusun
     * paket VPS untuk dijual, supaya tidak membuat paket yang ditolak
     * API saat provisioning.
     */
    public function getVmParameters(): array
    {
        return $this->callGlobal('get', '/api/parameters/vm');
    }

    /** Semua image OS (plain OS + app catalog). */
    public function listVmImages(): array
    {
        return $this->callGlobal('get', '/config/vm_images');
    }

    /** Image App Catalog saja (mis. WordPress siap pakai). */
    public function listAppCatalogImages(): array
    {
        return $this->callGlobal('get', '/config/vm_images/app_catalog');
    }

    /** ISO bootable (rescue / installer). */
    public function listBootImages(): array
    {
        return $this->callGlobal('get', '/config/boot_images');
    }

    /** Block storage / disk milik akun. */
    public function listDisks(): array
    {
        return $this->call('get', '/storage/disks');
    }

    /** Floating IP (IP publik yang bisa dipindah antar VM). */
    public function listFloatingIps(): array
    {
        return $this->call('get', '/network/ip_addresses');
    }

    /** Private network milik akun. */
    public function listNetworks(): array
    {
        return $this->call('get', '/network/networks');
    }

    /**
     * Daftar billing account + saldo kredit masing-masing.
     * Field penting: credit_amount, unpaid_amount, running_totals,
     * is_default, restriction_level, suspend_reason.
     */
    public function listBillingAccounts(): array
    {
        return $this->callGlobal('get', '/payment/billing_account/list');
    }

    /** Detail satu billing account (termasuk saldo & tunggakan). */
    public function getBillingAccountDetails(int|string $billingAccountId): array
    {
        return $this->callGlobal('get', '/payment/billing_account', ['billing_account_id' => $billingAccountId]);
    }

    /** Total tagihan yang belum dibayar (sudah termasuk pajak). */
    public function getUnpaidAmount(int|string $billingAccountId): array
    {
        return $this->callGlobal('get', '/payment/billing_account/unpaid_amount', ['billing_account_id' => $billingAccountId]);
    }

    /** Riwayat mutasi kredit (topup & pemakaian). */
    public function listCredit(int|string $billingAccountId): array
    {
        return $this->callGlobal('get', '/payment/credit/list', ['billing_account_id' => $billingAccountId]);
    }

    /**
     * HARGA MODAL per komponen dari IDCloudHost -- strukturnya persis
     * sama dengan formula harga jual kita (CPU, RAM, STORAGE main/
     * backup/snapshot, LICENSE windows), jadi bisa langsung
     * dibandingkan untuk menghitung margin.
     *
     * resourceType yang dikembalikan: CPU, RAM, STORAGE
     * (serviceNameInUptime: main/backup/snapshot), LICENSE (windows),
     * OBJECT_STORAGE. Semua harga per JAM.
     */
    public function getPricingPolicy(): array
    {
        return $this->callGlobal('get', '/pricing/policy');
    }

    /** Pemakaian & biaya bulan berjalan per resource. */
    public function getResourceUsage(int|string $billingAccountId): array
    {
        return $this->callGlobal('get', '/charging/usage', ['billing_account_id' => $billingAccountId]);
    }

    /**
     * Panggilan ke endpoint yang TIDAK location-specific (config, user,
     * parameters, payment, pricing) -- ini harus tanpa slug lokasi di
     * URL-nya, beda dengan resource VM/network yang wajib pakai slug.
     */
    protected function callGlobal(string $method, string $path, array $data = []): array
    {
        try {
            $response = Http::withHeaders(['apikey' => $this->server->api_token])
                ->baseUrl('https://api.idcloudhost.com/v1')
                ->timeout(30)
                ->{$method}($path, $data);

            $body = $response->json();

            if ($response->successful()) {
                return ['success' => true, 'message' => 'OK', 'raw' => $body];
            }

            $message = $this->extractError($body, $response->status(), $method, $path, $data);

            return ['success' => false, 'message' => $message, 'raw' => $body];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Tidak bisa terhubung: ' . $e->getMessage(), 'raw' => null];
        }
    }

    /**
     * Billing Account ID yang benar-benar bisa dipakai.
     *
     * Alokasi Floating IP MEWAJIBKAN billing_account_id berupa angka.
     * Kalau kolom di server tidak diisi (atau diisi teks), ID-nya
     * dicari otomatis dari daftar billing account -- diambil yang
     * default. Tanpa ini, alokasi IP selalu gagal padahal akunnya ada.
     */
    protected function resolveBillingAccountId(): ?int
    {
        if ($id = $this->billingAccountId()) {
            return $id;
        }

        $result = $this->listBillingAccounts();

        if (! $result['success'] || ! is_array($result['raw'])) {
            return null;
        }

        $accounts = collect($result['raw']);
        $default = $accounts->firstWhere('is_default', true) ?? $accounts->first();

        return isset($default['id']) ? (int) $default['id'] : null;
    }

    /**
     * Alokasikan IP publik baru. IDCloudHost tidak memberi IP publik
     * secara otomatis saat VM dibuat -- harus dialokasikan terpisah
     * lalu ditempelkan ke VM. PERHATIAN: IP yang dialokasikan menagih
     * biaya meski belum dipakai (lihat UNASSIGNED_FLOATING_IP di
     * pricing policy).
     */
    public function createFloatingIp(?string $name = null): array
    {
        $billingId = $this->resolveBillingAccountId();

        if (! $billingId) {
            return [
                'success' => false,
                'message' => 'Billing Account ID tidak diketahui — isi kolom Billing Account ID di pengaturan server dengan ANGKA, atau pastikan API token punya akses ke daftar billing account.',
                'raw' => null,
            ];
        }

        return $this->call('post', '/network/ip_addresses', array_filter([
            'billing_account_id' => $billingId,
            'name' => $name,
        ], fn ($v) => $v !== null), 60);
    }

    /** Tempelkan IP publik ke sebuah VM. */
    public function assignFloatingIp(string $address, string $vmUuid): array
    {
        return $this->call('post', "/network/ip_addresses/{$address}/assign", [
            'assigned_to' => $vmUuid,
            'assigned_to_resource_type' => 'virtual_machine',
        ], 60);
    }

    /** Lepaskan IP dari VM (IP tetap dimiliki & tetap menagih biaya). */
    public function unassignFloatingIp(string $address): array
    {
        return $this->call('post', "/network/ip_addresses/{$address}/unassign", [], 60);
    }

    /** Kembalikan IP ke provider supaya berhenti menagih biaya. */
    public function deleteFloatingIp(string $address): array
    {
        return $this->call('delete', "/network/ip_addresses/{$address}", [], 60);
    }

    /**
     * Alokasikan IP baru lalu langsung tempelkan ke VM -- dua langkah
     * yang hampir selalu dilakukan bersamaan.
     *
     * Kalau sudah ada IP menganggur di akun, IP itu yang dipakai
     * supaya tidak menumpuk IP tak terpakai yang sama-sama menagih.
     */
    public function attachPublicIp(string $vmUuid, ?string $label = null): array
    {
        $existing = $this->listFloatingIps();

        if ($existing['success'] && is_array($existing['raw'])) {
            $nganggur = collect($existing['raw'])->first(fn ($ip) => empty($ip['assigned_to']));

            if ($nganggur && ! empty($nganggur['address'])) {
                $assign = $this->assignFloatingIp($nganggur['address'], $vmUuid);

                return $assign['success']
                    ? $assign + ['address' => $nganggur['address'], 'message' => "IP {$nganggur['address']} (sudah ada, sebelumnya menganggur) berhasil dipasang."]
                    : $assign;
            }
        }

        $created = $this->createFloatingIp($label);

        if (! $created['success']) {
            return $created;
        }

        $address = $created['raw']['address'] ?? null;

        if (! $address) {
            return ['success' => false, 'message' => 'IP berhasil dialokasikan tapi alamatnya tidak dikembalikan provider.', 'raw' => $created['raw']];
        }

        $assign = $this->assignFloatingIp($address, $vmUuid);

        return $assign['success']
            ? $assign + ['address' => $address, 'message' => "IP {$address} berhasil dialokasikan dan dipasang."]
            : $assign + ['message' => "IP {$address} dialokasikan tapi gagal dipasang: " . $assign['message']];
    }

    public function toggleAutoBackup(string $uuid): array
    {
        return $this->call('post', '/user-resource/vm/backup', ['uuid' => $uuid]);
    }
}
