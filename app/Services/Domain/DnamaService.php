<?php

namespace App\Services\Domain;

use App\Models\Registrar;
use App\Services\Domain\Contracts\DomainRegistrarInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Integrasi DNAMA (Daftar Nama) -- registrar Indonesia yang mendukung
 * domain .id dan turunannya, plus manajemen DNS, DNSSEC, dan child
 * nameserver langsung lewat API.
 *
 * Sumber: dokumen "API for Reseller" v1.4 (OAS3), diberikan oleh
 * pengguna -- endpoint & skema payload diambil persis dari situ.
 *
 * ── AUTENTIKASI (terkonfirmasi dari tombol "Authorize" di
 *    api.dnama.id/docs) ──
 * Skema: ApiKeyAuth -- header "X-API-Key", isinya API Key mentah
 * (bukan format "Bearer ..."). Disimpan di kolom `api_key` pada data
 * Registrar -- SAMA PERSIS field yang sudah dipakai provider lain di
 * sistem ini.
 *
 * ── Ringkasan API ──
 * Base URL   : https://api.dnama.id       (isi field "API URL" di form
 *              Registrar kalau ternyata beda / ada versi sandbox)
 * Auth       : Header X-API-Key (lihat di atas)
 * Format     : JSON di semua respons
 *
 * ── Beda dari Liqu.id ──
 * DNAMA TIDAK memakai model berjenjang customer→contact→domain seperti
 * Liqu.id -- data kontak dikirim LANGSUNG di body request registrasi/
 * transfer (name, email, address_1/2/3, city, province, country,
 * postal_code, phone_number, mobile_phone_number), jadi jauh lebih
 * sederhana. Ada opsi `with_existing_customer` + `username`/`password`
 * kalau mau memakai akun customer yang sudah ada di DNAMA, alih-alih
 * membuat baru tiap kali.
 */
class DnamaService implements DomainRegistrarInterface
{
    protected const DEFAULT_BASE_URL = 'https://api.dnama.id';

    /**
     * TLD yang field "duration" (lama tahun) di endpoint TRANSFER-nya
     * benar-benar dipakai -- sesuai catatan resmi di dokumen: "Duration
     * properties is only for ID domain set [.id, .my.id, .co.id]".
     * Untuk TLD lain, transfer selalu otomatis menambah 1 tahun (aturan
     * umum ICANN), field duration diabaikan API meski tetap dikirim.
     */
    protected const TRANSFER_DURATION_TLDS = ['.id', '.my.id', '.co.id'];

    public function __construct(protected Registrar $registrar) {}

    // ─────────────────────────────────────────────────────────────
    // Kontrak wajib (DomainRegistrarInterface)
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /domain-availability
     */
    public function checkAvailability(array $domains): array
    {
        $results = [];
        $lastRaw = null;
        $errors = [];

        // Dokumen tidak menyebutkan endpoint ini bisa cek banyak domain
        // sekaligus dalam satu request (parameter tidak dijabarkan di
        // teks yang tersedia) -- dicek satu-satu supaya aman, mengikuti
        // pola AVAILABILITY_CHUNK di LiquidService kalau ternyata nanti
        // butuh dibatasi lebih lanjut.
        foreach ($domains as $domain) {
            $result = $this->call('get', '/domain-availability', ['domain_name' => $domain]);
            $lastRaw = $result['raw'];

            if (! $result['success']) {
                $errors[] = "{$domain}: {$result['message']}";
                continue;
            }

            $body = $result['raw'];
            // PENTING: dikonfirmasi dari dokumen resmi -- respons sukses
            // bentuknya {"data": {"available": true, "is_premium": true}}.
            // Field-nya "available" (bukan "is_available"), dan SELALU
            // bersarang di dalam "data". Sebelumnya kode ini mencoba
            // beberapa nama field yang TIDAK ADA satupun cocok dengan
            // struktur asli, sehingga availability selalu terbaca false.
            $available = (bool) ($body['data']['available'] ?? false);
            $results[$domain] = $available;
        }

        if (empty($results) && $errors) {
            return ['success' => false, 'message' => implode('; ', $errors), 'results' => [], 'raw' => $lastRaw];
        }

        return ['success' => true, 'message' => $errors ? implode('; ', $errors) : 'OK', 'results' => $results, 'raw' => $lastRaw];
    }

    /**
     * POST /domains
     *
     * $params:
     *   - domain (string), years (int), nameservers (string[], opsional)
     *   - contact (array): name, email, company_name, address1/2/3,
     *     city, province, country, postal_code, phone, mobile_phone
     *   - with_existing_customer (bool, opsional), username/password
     *     (opsional, dipakai kalau with_existing_customer true ATAU
     *     untuk membuat akun customer baru di DNAMA)
     */
    public function registerDomain(array $params): array
    {
        $payload = array_merge([
            'domain_name'   => $params['domain'],
            'duration'      => $params['years'] ?? 1,
            'nameservers'   => array_values((array) ($params['nameservers'] ?? [])),
            'with_existing_customer' => (bool) ($params['with_existing_customer'] ?? false),
        ], $this->contactPayload($params['contact'] ?? []));

        // Password wajib diisi API DNAMA baik untuk bikin customer baru
        // maupun memakai yang sudah ada -- dibuatkan otomatis kalau
        // checkout tidak mengirimkannya, supaya alur tidak gagal cuma
        // karena field ini kosong.
        if (empty($payload['password'])) {
            $payload['password'] = $this->generatePassword();
        }

        if (empty($payload['username']) && ! empty($payload['email'])) {
            $payload['username'] = $payload['email'];
        }

        return $this->call('post', '/domains', $payload);
    }

    /**
     * POST /domain/transfer
     *
     * Sama seperti registrar lain: transfer bukan langsung pindah detik
     * itu juga, ada persetujuan pemilik lama + waktu tunggu dari
     * registry -- status domain di sistem kita tetap "pending" sampai
     * admin pastikan transfer-nya benar-benar selesai.
     */
    public function transferDomain(array $params): array
    {
        $payload = array_merge([
            'domain_name' => $params['domain'],
            'epp_code'    => $params['auth_code'] ?? '',
            'with_existing_customer' => (bool) ($params['with_existing_customer'] ?? false),
        ], $this->contactPayload($params['contact'] ?? []));

        // "Duration properties is only for ID domain set [.id, .my.id,
        // .co.id]" -- dikirim cuma untuk TLD itu, biar tidak membingungkan
        // kalau API menganggapnya berlaku juga untuk TLD lain.
        $extension = '.' . \Illuminate\Support\Str::after($params['domain'], '.');
        if (in_array($extension, self::TRANSFER_DURATION_TLDS, true)) {
            $payload['duration'] = $params['years'] ?? 1;
        }

        if (empty($payload['password'])) {
            $payload['password'] = $this->generatePassword();
        }

        if (empty($payload['username']) && ! empty($payload['email'])) {
            $payload['username'] = $payload['email'];
        }

        return $this->call('post', '/domain/transfer', $payload);
    }

    /**
     * POST /domains/{domain_name}/renew
     *
     * Body butuh current_expiry_date -- diambil dari sistem kita kalau
     * tidak dikirim eksplisit, karena DNAMA butuh titik acuan tanggal
     * (bukan cuma "tambah N tahun dari sekarang").
     */
    /**
     * Dnama MEWAJIBKAN current_expiry_date SAMA PERSIS dengan tanggal
     * expiry yang tercatat di sisi mereka -- dokumen resmi eksplisit
     * menyebut error "Current expiry date does not match with
     * domain's expiry date" kalau meleset.
     *
     * TIDAK memakai now() sebagai tebakan (itu hampir pasti beda dari
     * expiry sungguhan, bikin renew SELALU ditolak) -- diambil
     * otomatis dari data domain yang SUDAH ADA di database Lumora
     * sendiri, supaya method ini tetap bisa dipanggil generik lewat
     * interface (sama seperti provider lain) tanpa pemanggilnya perlu
     * tahu soal kuirk khusus Dnama ini.
     */
    public function renewDomain(string $domain, int $years): array
    {
        $expiryDate = \App\Models\Domain::where('domain_name', $domain)->value('expiry_date');

        if (! $expiryDate) {
            return [
                'success' => false,
                'message' => "Tidak menemukan tanggal expiry untuk {$domain} di database Lumora -- Dnama mewajibkan tanggal ini persis sama dengan catatan mereka. Pastikan domain sudah tersimpan dengan expiry_date terisi, atau pakai renewDomainWithExpiry() manual dengan tanggal yang benar.",
                'raw' => null,
            ];
        }

        $formatted = $expiryDate instanceof \Carbon\Carbon ? $expiryDate->format('Y-m-d') : \Carbon\Carbon::parse($expiryDate)->format('Y-m-d');

        return $this->renewDomainWithExpiry($domain, $years, $formatted);
    }

    /**
     * Sama seperti renewDomain(), tapi dipakai kalau tanggal jatuh tempo
     * SUNGGUHAN sudah diketahui di sistem kita (lebih akurat daripada
     * memakai tanggal hari ini) -- lihat parameter current_expiry_date
     * di dokumen API.
     */
    public function renewDomainWithExpiry(string $domain, int $years, string $currentExpiryDate): array
    {
        return $this->call('post', "/domains/{$domain}/renew", [
            'current_expiry_date' => $currentExpiryDate,
            'duration' => $years,
        ]);
    }

    /**
     * PUT /domains/{domain_name}/nameservers
     */
    public function setNameservers(string $domain, array $nameservers): array
    {
        return $this->call('put', "/domains/{$domain}/nameservers", [
            'nameservers' => array_values($nameservers),
        ]);
    }

    /**
     * Uji kredensial dengan request ringan yang tidak mengubah data --
     * cek saldo, sama seperti sekadar "siapa saya".
     */
    public function testConnection(): array
    {
        $result = $this->call('get', '/my/balance');

        if ($result['success']) {
            $result['message'] = 'Kredensial DNAMA valid.';
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────
    // Domain -- tambahan di luar kontrak wajib
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /domains/{domain_name}
     */
    public function getDomainInfo(string $domain): array
    {
        return $this->call('get', "/domains/{$domain}");
    }

    /**
     * POST /domains/{domain_name}/restore
     * Mengaktifkan kembali domain yang statusnya redemption/expired
     * (kalau registry & DNAMA masih mengizinkan restore).
     */
    public function restoreDomain(string $domain): array
    {
        return $this->call('post', "/domains/{$domain}/restore");
    }

    /**
     * PUT /domains/{domain_name}/is_enable_update_protection
     */
    public function setUpdateProtection(string $domain, bool $enabled): array
    {
        return $this->call('put', "/domains/{$domain}/is_enable_update_protection", [
            'is_enable_update_protection' => $enabled,
        ]);
    }

    /**
     * PUT /domains/{domain_name}/is_enable_transfer_protection
     * Ini yang berfungsi sebagai "registrar lock" di DNAMA -- mengunci
     * domain supaya tidak bisa ditransfer keluar tanpa sepengetahuan.
     */
    public function setTransferProtection(string $domain, bool $enabled): array
    {
        return $this->call('put', "/domains/{$domain}/is_enable_transfer_protection", [
            'is_enable_transfer_protection' => $enabled,
        ]);
    }

    /**
     * GET /domains/{domain_name}/upload_document_url
     * Dipakai untuk domain yang butuh verifikasi dokumen (mis. .id
     * dengan KTP/NPWP) -- mengembalikan URL tempat klien bisa upload
     * dokumennya langsung ke DNAMA.
     */
    public function getUploadDocumentUrl(string $domain): array
    {
        return $this->call('get', "/domains/{$domain}/upload_document_url");
    }

    // ─────────────────────────────────────────────────────────────
    // DNS Management
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /domains/{domain_name}/dns-records
     */
    public function listDnsRecords(string $domain): array
    {
        return $this->call('get', "/domains/{$domain}/dns-records");
    }

    /**
     * POST /domains/{domain_name}/dns-records
     *
     * @param  string  $subDomain  mis. "@" untuk root, "www" untuk www.domain
     * @param  string  $type  A / AAAA / CNAME / MX / TXT / dst.
     */
    public function addDnsRecord(string $domain, string $subDomain, string $type, string $address, int $ttl = 3600): array
    {
        return $this->call('post', "/domains/{$domain}/dns-records", [
            'sub_domain' => $subDomain,
            'type' => strtoupper($type),
            'ttl' => $ttl,
            'address' => $address,
        ]);
    }

    /**
     * DELETE /domains/{domain_name}/dns-records/{dns_record_id}
     */
    public function deleteDnsRecord(string $domain, string $dnsRecordId): array
    {
        return $this->call('delete', "/domains/{$domain}/dns-records/{$dnsRecordId}");
    }

    // ─────────────────────────────────────────────────────────────
    // Nameserver Anak (child nameserver / glue record)
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /domains/{domain_name}/child-nameservers
     */
    public function listChildNameservers(string $domain): array
    {
        return $this->call('get', "/domains/{$domain}/child-nameservers");
    }

    /**
     * POST /domains/{domain_name}/child-nameservers
     * mis. addChildNameserver('contoh.com', 'ns1', '192.123.80.79')
     * membuat ns1.contoh.com -> 192.123.80.79
     */
    public function addChildNameserver(string $domain, string $subdomain, string $ipAddress): array
    {
        return $this->call('post', "/domains/{$domain}/child-nameservers", [
            'subdomain' => $subdomain,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * DELETE /domains/{domain_name}/child-nameservers/{sub_domain}
     */
    public function deleteChildNameserver(string $domain, string $subdomain): array
    {
        return $this->call('delete', "/domains/{$domain}/child-nameservers/{$subdomain}");
    }

    // ─────────────────────────────────────────────────────────────
    // DNSSEC
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /domains/dnssec/algorithms
     * Daftar ID algoritma DNSSEC yang didukung -- statis dari sisi
     * DNAMA, aman di-cache lama kalau nanti mau dioptimalkan.
     */
    public function getDnssecAlgorithms(): array
    {
        return $this->call('get', '/domains/dnssec/algorithms');
    }

    /**
     * GET /domains/dnssec/digest-types
     */
    public function getDnssecDigestTypes(): array
    {
        return $this->call('get', '/domains/dnssec/digest-types');
    }

    /**
     * GET /domains/{domain_name}/dnssec
     */
    public function getDnssecStatus(string $domain): array
    {
        return $this->call('get', "/domains/{$domain}/dnssec");
    }

    /**
     * POST /domains/{domain_name}/dnssec
     * Aktifkan DNSSEC dengan mengirim DS record -- key_tag, algorithm,
     * dan digest_type mengacu ke ID dari getDnssecAlgorithms() /
     * getDnssecDigestTypes(), bukan nama bebas.
     */
    public function enableDnssec(string $domain, int $keyTag, int $algorithm, int $digestType, string $digest): array
    {
        return $this->call('post', "/domains/{$domain}/dnssec", [
            'key_tag' => $keyTag,
            'algorithm' => $algorithm,
            'digest_type' => $digestType,
            'digest' => $digest,
        ]);
    }

    /**
     * DELETE /domains/{domain_name}/dnssec
     */
    public function disableDnssec(string $domain): array
    {
        return $this->call('delete', "/domains/{$domain}/dnssec");
    }

    // ─────────────────────────────────────────────────────────────
    // Customer
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /customers
     * Biasanya tidak perlu dipanggil manual -- registerDomain() dan
     * transferDomain() sudah membuat customer otomatis kalau
     * with_existing_customer=false. Disediakan terpisah untuk kasus
     * admin mau menyiapkan akun customer duluan sebelum ada pesanan.
     */
    public function createCustomer(array $contact, string $username, string $password): array
    {
        return $this->call('post', '/customers', array_merge(
            ['username' => $username, 'password' => $password],
            $this->contactPayload($contact, includePhoneSplit: false)
        ));
    }

    /**
     * GET /customers/{username}
     */
    public function getCustomer(string $username): array
    {
        return $this->call('get', "/customers/{$username}");
    }

    // ─────────────────────────────────────────────────────────────
    // Kontak (registrant / technical / billing / admin)
    // ─────────────────────────────────────────────────────────────

    public function updateRegistrantContact(string $domain, array $contact): array
    {
        return $this->call('put', "/domains/{$domain}/registrant_contact", $this->contactPayload($contact, includePhoneSplit: false, includeOrganization: true));
    }

    public function updateTechnicalContact(string $domain, array $contact): array
    {
        return $this->call('put', "/domains/{$domain}/technical_contact", $this->contactPayload($contact, includePhoneSplit: false, includeOrganization: true));
    }

    public function updateBillingContact(string $domain, array $contact): array
    {
        return $this->call('put', "/domains/{$domain}/billing_contact", $this->contactPayload($contact, includePhoneSplit: false, includeOrganization: true));
    }

    public function updateAdminContact(string $domain, array $contact): array
    {
        return $this->call('put', "/domains/{$domain}/admin_contact", $this->contactPayload($contact, includePhoneSplit: false, includeOrganization: true));
    }

    // ─────────────────────────────────────────────────────────────
    // Harga & Saldo
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /tld-pricings
     * Harga MODAL (yang dibebankan ke saldo reseller kita) -- ini yang
     * dipakai fitur "Tarik Harga Registrar" di TLD Pricing, BUKAN
     * customer-tld-pricings (itu harga yang DNAMA sarankan untuk
     * pelanggan mereka sendiri, bukan patokan modal kita).
     */
    public function listTldPricings(): array
    {
        return $this->fetchAllTldPricings();
    }

    /**
     * Ambil SELURUH baris /tld-pricings, mengikuti pagination.
     *
     * PENYEBAB SEBENARNYA hanya 2 TLD yang tersinkron: respons DNAMA
     * ternyata dibungkus pagination ala Laravel resource collection
     * ({"data": [...], "links": {...}, "meta": {"current_page": 1,
     * "last_page": N, ...}}), tapi kode sebelumnya cuma memanggil
     * endpoint SEKALI dan langsung ambil "data" tanpa pernah melihat
     * "meta.last_page" -- jadi TLD-TLD di luar halaman pertama (yang
     * kebetulan penuh berisi varian premium ".id") tidak pernah
     * kepanggil sama sekali. Method ini mengulang panggilan dengan
     * parameter "page" sampai halaman terakhir, lalu menggabungkan
     * semua baris "data" jadi satu array -- sama seperti kalau tidak
     * ada pagination, "meta" akan absen dan loop berhenti di page 1.
     *
     * @return array{success: bool, message: string, raw: array{data: array}}
     */
    private function fetchAllTldPricings(): array
    {
        $all = [];
        $page = 1;
        $lastPage = 1;

        do {
            $result = $this->call('get', '/tld-pricings', ['page' => $page], timeout: 60);

            if (! $result['success']) {
                // Kalau sudah dapat sebagian halaman lalu gagal di
                // tengah jalan, lebih baik tetap kembalikan apa yang
                // sudah terkumpul daripada membuang semuanya -- tapi
                // pesan errornya tetap disampaikan lewat log supaya
                // ketahuan kalau sinkron jadi tidak lengkap.
                if ($all) {
                    Log::warning("DNAMA /tld-pricings: berhenti di halaman {$page} -- {$result['message']}");
                    break;
                }

                return $result;
            }

            $body = $result['raw'];
            $rows = $body['data'] ?? (is_array($body) ? $body : []);
            $all = array_merge($all, $rows);

            // Kalau tidak ada key "meta" sama sekali, berarti endpoint
            // ini memang tidak dipaginasi -- loop otomatis berhenti di
            // page 1 seperti perilaku lama, tidak ada perubahan.
            $lastPage = $body['meta']['last_page'] ?? 1;
            $page++;
        } while ($page <= $lastPage);

        return ['success' => true, 'message' => 'OK', 'raw' => ['data' => $all]];
    }

    /**
     * GET /customer-tld-pricings
     */
    public function listCustomerTldPricings(): array
    {
        return $this->call('get', '/customer-tld-pricings', timeout: 60);
    }

    /**
     * GET /sub-reseller-tld-pricings
     */
    public function listSubResellerTldPricings(): array
    {
        return $this->call('get', '/sub-reseller-tld-pricings', timeout: 60);
    }

    /**
     * GET /my/balance
     */
    public function getBalance(): array
    {
        return $this->call('get', '/my/balance');
    }

    // ─────────────────────────────────────────────────────────────
    // Sinkronisasi TLD -- dipanggil RegistrarController::syncTlds()
    // lewat method_exists(), jadi NAMA & BENTUK RETURN harus persis
    // cocok (lihat pola listTlds()/listPrices() di provider lain).
    // ─────────────────────────────────────────────────────────────

    /**
     * Daftar TLD yang tersedia -- dipakai untuk membuat baris baru di
     * tabel TLD Pricing. Harga TIDAK disertakan di sini secara
     * langsung (dibiarkan null), diambil terpisah lewat listPrices()
     * -- sama pembagian tanggung jawab dengan provider lain.
     *
     * CATATAN PENTING soal struktur asli Dnama: satu TLD punya BANYAK
     * tingkat harga per durasi tahun (pricings: [{duration:1,...},
     * {duration:2,...}]), TAPI tabel Tld di sistem kita cuma punya
     * SATU harga flat per aksi (register/renew/transfer -- lihat
     * kolom cost_register dkk di RegistrarController::syncTlds()).
     * Jadi dipakai tingkat DURASI 1 TAHUN sebagai harga acuan --
     * min_years/max_years di TLD sudah menangani konsep "boleh
     * dipesan sekian tahun", bukan harga per tahun beda-beda.
     */
    public function listTlds(): array
    {
        $result = $this->fetchAllTldPricings();

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'], 'tlds' => []];
        }

        $rows = $result['raw']['data'] ?? [];
        $tlds = [];

        foreach ($rows as $row) {
            // PENTING (ditemukan dari data mentah sungguhan akun ini):
            // Dnama mengirim BARIS TERPISAH untuk varian premium dari
            // ekstensi yang SAMA -- mis. ".id" biasa (Rp 215rb) DAN
            // ".id" premium 2-karakter (Rp 585 JUTA) sama-sama punya
            // "tld": ".id". Tanpa penyaringan ini, baris premium bisa
            // TERTUKAR/menimpa harga ekstensi biasa saat sinkronisasi
            // (dicocokkan cuma berdasarkan nama ekstensi, lihat
            // RegistrarController::syncTlds()). Domain premium juga
            // wajib lewat alur pemesanan terpisah menurut dokumen API
            // ("Premium domain can only be ordered in premium domain
            // order flow") -- tidak cocok dengan model "satu harga
            // flat per TLD" di tabel TLD Pricing kita, jadi baris
            // premium SENGAJA dilewati di sini.
            if (! empty($row['is_premium'])) {
                continue;
            }

            $oneYear = collect($row['pricings'] ?? [])->firstWhere('duration', 1);

            $tlds[] = [
                'extension' => $row['tld'] ?? '',
                'price' => $oneYear['register_price'] ?? null,
                'min_years' => 1,
                // Dnama tidak menyebut batas atas eksplisit di dokumen --
                // dipakai durasi tahun TERTINGGI yang tersedia di daftar
                // pricings sebagai perkiraan aman, jatuh balik ke 10
                // (default umum registrar) kalau array-nya kosong.
                'max_years' => collect($row['pricings'] ?? [])->max('duration') ?: 10,
            ];
        }

        return ['success' => true, 'message' => 'OK', 'tlds' => $tlds];
    }

    /**
     * Harga modal per TLD (register/renew/transfer) -- dipakai
     * mengisi kolom cost_* di tabel TLD Pricing. Sama seperti
     * listTlds(), dipakai tingkat durasi 1 tahun sebagai acuan flat.
     */
    public function listPrices(): array
    {
        $result = $this->fetchAllTldPricings();

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['message'], 'prices' => []];
        }

        $rows = $result['raw']['data'] ?? [];
        $prices = [];

        foreach ($rows as $row) {
            $ext = $row['tld'] ?? null;

            if (! $ext) {
                continue;
            }

            // Sama seperti listTlds() -- baris premium (mis. ".id"
            // 2-karakter seharga ratusan juta) BERBAGI nama ekstensi
            // yang sama persis dengan baris ".id" biasa. Tanpa
            // penyaringan ini, harga premium bisa menimpa/tertimpa
            // harga biasa tergantung urutan array, bukan berdasarkan
            // mana yang genuinely dimaksud.
            if (! empty($row['is_premium'])) {
                continue;
            }

            $oneYear = collect($row['pricings'] ?? [])->firstWhere('duration', 1);

            if (! $oneYear) {
                continue;
            }

            // Semua durasi 1-10 tahun yang DNAMA sediakan disimpan sebagai
            // referensi harga modal per tahun (cost_year_prices) --
            // sebelumnya cuma duration=1 yang dipakai, padahal API-nya
            // sudah mengembalikan harga tiap durasi sekaligus.
            $yearPrices = [];
            $yearRenewPrices = [];

            foreach (($row['pricings'] ?? []) as $p) {
                $duration = $p['duration'] ?? null;

                if (! $duration || $duration == 1) {
                    continue; // duration=1 sudah terwakili oleh register/renew utama
                }

                if (isset($p['register_price'])) {
                    $yearPrices[(string) $duration] = (float) $p['register_price'];
                }

                if (isset($p['renewal_price'])) {
                    $yearRenewPrices[(string) $duration] = (float) $p['renewal_price'];
                }
            }

            $prices[$ext] = [
                'register' => (float) ($oneYear['register_price'] ?? 0),
                'renew' => (float) ($oneYear['renewal_price'] ?? 0),
                'transfer' => (float) ($oneYear['transfer_price'] ?? 0),
                'currency' => $row['currency'] ?? 'IDR',
                'year_prices' => $yearPrices ?: null,
                'year_renew_prices' => $yearRenewPrices ?: null,
            ];
        }

        return ['success' => true, 'message' => 'OK', 'prices' => $prices];
    }

    /**
     * GET /my/balance -- nama method disamakan dengan yang dicari
     * RegistrarController (getAccountBalance, bukan getBalance) lewat
     * method_exists(). Bentuk return DIRATAKAN (balance & currency
     * langsung di level atas) -- admin/registrars/index.blade.php
     * membaca $balances[$id]['balance'] langsung, bukan lewat
     * raw.data.balance yang masih bersarang.
     */
    public function getAccountBalance(): array
    {
        $result = $this->getBalance();

        if (! $result['success']) {
            return $result;
        }

        $data = $result['raw']['data'] ?? [];

        return [
            'success' => true,
            'message' => 'OK',
            'balance' => (float) ($data['balance'] ?? 0),
            'currency' => $data['currency'] ?? 'IDR',
            'raw' => $result['raw'],
        ];
    }

    /**
     * Contoh mentah 3 baris pertama dari /tld-pricings -- dipakai
     * halaman Diagnosa untuk menampilkan format harga SUNGGUHAN yang
     * dikembalikan Dnama, bukan tebakan. Endpoint yang sama dengan
     * listTlds()/listPrices(), cuma diambil apa adanya tanpa diolah.
     */
    public function getAccountPricesRaw(): array
    {
        // Dipakai halaman Diagnosa juga -- kalau ini tidak ikut
        // dipaginasi, angka "Total baris dari API" & "Ekstensi unik"
        // di sana akan tetap menunjukkan jumlah SEBELUM perbaikan
        // pagination, jadi salah membaca kondisi sebenarnya.
        $result = $this->fetchAllTldPricings();

        return ['success' => $result['success'], 'message' => $result['message'], 'raw' => $result['raw']['data'] ?? $result['raw']];
    }

    /**
     * Dnama tidak punya endpoint "detail akun" terpisah (nama
     * perusahaan, dsb) di dokumen yang tersedia -- tapi endpoint
     * saldo SUDAH menyertakan mata uang akun, jadi dipakai ulang di
     * sini. Nama key (selling_currency, name, company) disamakan
     * PERSIS dengan yang dibaca diagnostics.blade.php -- dikonfirmasi
     * langsung dari isi file itu, bukan tebakan.
     */
    public function getAccountDetails(): array
    {
        $result = $this->getBalance();

        if (! $result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'message' => 'OK',
            'selling_currency' => $result['raw']['data']['currency'] ?? null,
            // Dnama tidak mengembalikan nama/nama perusahaan reseller di
            // endpoint mana pun yang tersedia di dokumen -- dibiarkan
            // null (bukan dihilangkan) supaya view tidak error saat
            // membaca $details['name'] / $details['company'].
            'name' => null,
            'company' => null,
            'raw' => $result['raw'],
        ];
    }

    // Method opsional berikut ini TIDAK diimplementasikan dengan
    // sengaja: listCustomers(), getAccountTransactions() -- dokumen
    // resmi "API for Reseller" v1.4 yang tersedia TIDAK menyebutkan
    // endpoint yang cocok untuk fitur-fitur ini (Dnama cuma punya
    // "get SATU customer by username", bukan "list semua customer";
    // dan tidak ada endpoint riwayat transaksi sama sekali).
    // RegistrarController mengecek lewat method_exists() dan akan
    // menampilkan pesan "belum didukung" secara otomatis untuk method
    // yang tidak ada -- itu JUJUR sesuai kemampuan API yang
    // sebenarnya, bukan bug.
    //
    // Kalau ternyata Dnama punya endpoint untuk ini yang tidak
    // tercakup di dokumen yang diberikan, tambahkan method di sini
    // dengan nama PERSIS sama seperti yang dicari RegistrarController.

    // ─────────────────────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────────────────────

    /**
     * Susun payload kontak dari array kontak checkout ke bentuk yang
     * DNAMA harapkan -- dibuat toleran terhadap beberapa variasi nama
     * kolom (mis. "phone" atau "phone_number") supaya tidak gampang
     * patah kalau nama field di CheckoutController sedikit beda dari
     * dugaan.
     */
    private function contactPayload(array $c, bool $includePhoneSplit = true, bool $includeOrganization = false): array
    {
        $get = fn (array $keys, $default = '') => collect($keys)
            ->map(fn ($k) => $c[$k] ?? null)
            ->first(fn ($v) => filled($v)) ?? $default;

        $payload = [
            'name'          => $get(['name', 'full_name']),
            'company_name'  => $get(['company_name', 'company', 'organization']),
            'email'         => $get(['email']),
            'address_1'     => $get(['address_1', 'address1', 'address']),
            'address_2'     => $get(['address_2', 'address2'], ''),
            'address_3'     => $get(['address_3', 'address3'], ''),
            'city'          => $get(['city']),
            'province'      => $get(['province', 'state']),
            'country'       => $get(['country', 'country_code']),
            'postal_code'   => $get(['postal_code', 'zip', 'zip_code']),
            'phone_number'  => $get(['phone_number', 'phone']),
            'mobile_phone_number' => $get(['mobile_phone_number', 'mobile', 'phone']),
        ];

        if ($includeOrganization) {
            $payload['organization_name'] = $payload['company_name'];
            unset($payload['company_name']);
        }

        if (isset($c['username'])) {
            $payload['username'] = $c['username'];
        }

        if (isset($c['password'])) {
            $payload['password'] = $c['password'];
        }

        return $payload;
    }

    private function generatePassword(): int|string
    {
        // Minimal 8, maksimal 48 karakter sesuai spesifikasi dokumen.
        return \Illuminate\Support\Str::password(16, symbols: false);
    }

    protected function call(string $method, string $endpoint, array $params = [], ?int $timeout = null): array
    {
        try {
            $client = $this->client($timeout);

            $response = match ($method) {
                'post'   => $client->post($endpoint, $params),
                'put'    => $client->put($endpoint, $params),
                'delete' => $client->delete($endpoint, $params),
                default  => $client->get($endpoint, $params),
            };

            $body = $response->json();

            if (in_array($response->status(), [401, 403], true)) {
                return [
                    'success' => false,
                    'message' => 'Autentikasi ditolak. Cek API Key DNAMA -- kemungkinan juga skema autentikasinya beda dari yang diasumsikan (lihat catatan di atas kelas ini).',
                    'raw' => $body,
                ];
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->extractError($body) ?? "DNAMA mengembalikan HTTP {$response->status()}.",
                    'raw' => $body ?? $response->body(),
                ];
            }

            return ['success' => true, 'message' => 'OK', 'raw' => $body];
        } catch (Throwable $e) {
            Log::warning("DNAMA API [{$method} {$endpoint}] gagal: " . $e->getMessage(), [
                'registrar_id' => $this->registrar->id,
            ]);

            return [
                'success' => false,
                'message' => 'Tidak bisa terhubung ke DNAMA: ' . $e->getMessage(),
                'raw' => null,
            ];
        }
    }

    /**
     * Format error belum diketahui pasti dari dokumen yang ada (tidak
     * ada contoh body error eksplisit) -- dicoba beberapa kunci umum
     * (message/error/errors), jatuh balik ke null kalau tidak ada yang
     * cocok supaya call() pakai pesan generik HTTP status.
     */
    protected function extractError(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        if (! empty($body['message'])) {
            return (string) $body['message'];
        }

        if (! empty($body['error'])) {
            return is_array($body['error']) ? json_encode($body['error']) : (string) $body['error'];
        }

        if (! empty($body['errors'])) {
            return is_array($body['errors']) ? implode('; ', array_map('strval', $body['errors'])) : (string) $body['errors'];
        }

        return null;
    }

    protected function client(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders(['X-API-Key' => (string) $this->registrar->api_key])
            ->acceptJson()
            ->asJson()
            ->timeout($timeout ?? 25);
    }

    protected function baseUrl(): string
    {
        if (filled($this->registrar->api_url)) {
            return rtrim($this->registrar->api_url, '/');
        }

        return self::DEFAULT_BASE_URL;
    }
}