<?php

namespace App\Services\Provisioning;

use App\Models\ActivityLog;
use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\Order;
use App\Notifications\OrderProvisioned;
use App\Services\Domain\DomainRegistrarFactory;
use App\Services\Hosting\HostingPanelFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Dipanggil otomatis lewat event Invoice::updated() saat status invoice
 * berubah jadi "paid" (lihat App\Models\Invoice) — baik itu dari webhook
 * gateway, approve transfer manual, atau admin edit langsung. Semua jalur
 * pelunasan berujung ke sini.
 *
 * Untuk tiap order di invoice: hosting → buat akun cPanel via Fase 3,
 * domain → registrasi via Fase 4. Kegagalan satu item TIDAK menghentikan
 * item lain — dicatat di provision_message masing-masing supaya admin
 * bisa menindaklanjuti manual.
 */
class ProvisioningService
{
    public function provisionInvoice(Invoice $invoice): void
    {
        $orders = $this->resolveOrders($invoice);

        if ($orders->isEmpty()) {
            return;
        }

        $hostingCredentials = [];
        $domainResults = [];

        foreach ($orders as $order) {
            try {
                if ($order->order_type === 'hosting' && $order->hostingAccount) {
                    $cred = $this->provisionHosting($order->hostingAccount, $order);
                    if ($cred) {
                        $hostingCredentials[] = $cred;
                    }
                } elseif ($order->order_type === 'domain' && $order->domain) {
                    $result = $this->provisionDomain($order->domain, $order);
                    if ($result) {
                        $domainResults[] = $result;
                    }
                }
            } catch (Throwable $e) {
                Log::error("Provisioning order #{$order->id} gagal: " . $e->getMessage(), ['order_id' => $order->id]);
            }

            // Order dianggap selesai dari sisi billing begitu invoice lunas,
            // terlepas dari sukses/gagalnya provisioning otomatis — status
            // provisioning detail ada di provision_status hosting/domain-nya.
            if ($order->status === 'pending') {
                $order->update(['status' => 'active']);
            }
        }

        if ($hostingCredentials || $domainResults) {
            $this->notifyClient($invoice, $hostingCredentials, $domainResults);
        }
    }

    /**
     * Order dari invoice_items (checkout Fase 7c) kalau ada, atau fallback
     * ke relasi order() tunggal untuk invoice manual lama (Fase 2).
     */
    private function resolveOrders(Invoice $invoice): \Illuminate\Support\Collection
    {
        $invoice->loadMissing(['items.order.hostingAccount.serverModel', 'items.order.domain.registrar', 'order']);

        if ($invoice->items->isNotEmpty()) {
            return $invoice->items->pluck('order')->filter()->unique('id')->values();
        }

        return collect([$invoice->order])->filter()->values();
    }

    /**
     * @return array{domain: string, username: string, password: string}|null
     */
    private function provisionHosting(HostingAccount $account, Order $order): ?array
    {
        // Sudah pernah diprovision (mis. event ini terpicu lagi) — jangan
        // buat akun dobel di server.
        if ($account->provision_status === 'provisioned') {
            return null;
        }

        // Tidak ada server tujuan = memang manual, bukan kegagalan.
        if (! $account->server_id) {
            return null;
        }

        $server = $account->serverModel;

        if (! $server) {
            $account->update(['provision_status' => 'failed', 'provision_message' => 'Server tujuan tidak ditemukan.']);

            return null;
        }

        $username = $account->username ?: $this->generateUsername($account->domain);
        $password = $this->generatePassword();

        $result = HostingPanelFactory::make($server)->createAccount([
            'domain'   => $account->domain,
            'username' => $username,
            'password' => $password,
            'package'  => $account->package,
            'email'    => $order->client->email ?? '',
        ]);

        // PENTING untuk provider VM/cloud (mis. IDCloudHost): createAccount()
        // mengembalikan UUID VM sebagai 'username' -- itulah SATU-SATUNYA
        // pengenal yang dipakai semua operasi berikutnya (start/stop/
        // hapus/resize/ganti password). Kalau ditimpa dengan username
        // hasil generate ala cPanel, semua operasi VM setelah ini akan
        // menunjuk ke resource yang tidak ada.
        $identifier = $result['username'] ?? $username;

        $updates = [
            'username'          => $identifier,
            'status'            => $result['success'] ? 'active' : $account->status,
            'provision_status'  => $result['success'] ? 'provisioned' : 'failed',
            'provision_message' => $result['message'],
        ];

        // IP hasil provisioning VM disimpan ke info akses klien, supaya
        // klien langsung tahu alamat servernya tanpa harus tanya support.
        if ($result['success'] && ! empty($result['ip'])) {
            $updates['client_details'] = trim(
                (string) $account->client_details . "\n"
                . "IP Server: {$result['ip']}\n"
                . "Username: {$username}\n"
                . "Password: {$password}"
            );
        }

        $account->update($updates);

        if (! $result['success']) {
            return null;
        }

        // Kalau domain ini juga terdaftar lewat sistem kita sendiri (klien
        // yang sama), otomatis arahkan nameservernya ke server hosting
        // ini — supaya klien tidak perlu atur manual lagi setelah beli
        // hosting. Kegagalan di sini TIDAK membatalkan hosting yang sudah
        // berhasil dibuat, cuma dicatat.
        //
        // Diutamakan nameserver yang BENAR-BENAR dikembalikan WHM lewat
        // createacct() (paling akurat, langsung dari server) -- baru
        // jatuh ke pengaturan manual Server->ns1/ns2 kalau WHM tidak
        // mengembalikannya untuk alasan apa pun.
        $nameservers = $result['nameservers'] ?? array_values(array_filter([$server->ns1, $server->ns2]));

        if (count($nameservers) >= 2) {
            $matchingDomain = Domain::where('domain_name', $account->domain)
                ->where('client_id', $order->client_id)
                ->where('provision_status', 'registered')
                ->first();

            if ($matchingDomain && $matchingDomain->registrar) {
                try {
                    $nsResult = DomainRegistrarFactory::make($matchingDomain->registrar)
                        ->setNameservers($matchingDomain->domain_name, $nameservers);

                    if ($nsResult['success']) {
                        $matchingDomain->update(['nameservers' => $nameservers]);
                    } else {
                        Log::warning('Auto-arahkan nameserver ke hosting gagal: ' . $nsResult['message'], [
                            'domain_id' => $matchingDomain->id,
                            'hosting_account_id' => $account->id,
                        ]);
                    }
                } catch (Throwable $e) {
                    Log::warning('Auto-arahkan nameserver ke hosting error: ' . $e->getMessage(), [
                        'domain_id' => $matchingDomain->id,
                    ]);
                }
            }
        }

        return ['domain' => $account->domain, 'username' => $username, 'password' => $password];
    }

    /**
     * @return array{domain: string, success: bool, message: string}|null
     */
    private function provisionDomain(Domain $domain, Order $order): ?array
    {
        if (in_array($domain->provision_status, ['registered', 'transfer_pending'], true)) {
            return null;
        }

        // TLD tanpa registrar (mis. TLD demo ".test" dari lumora:demo-tld,
        // atau domain yang sengaja diproses manual) — TIDAK ada API untuk
        // dipanggil, tapi domainnya tetap harus ditandai aktif di database.
        //
        // Sebelumnya di sini langsung `return null` tanpa menyentuh status
        // domain sama sekali. Order tetap ditandai "Active" oleh
        // provisionInvoice() (baris terpisah, tidak bergantung ke sini),
        // sehingga admin melihat order aktif padahal Domain::status masih
        // "pending" — itu sebabnya dashboard klien menghitung 0 domain
        // aktif meski order-nya sudah "Active".
        if (! $domain->registrar_id) {
            $domain->update([
                'status' => 'active',
                'register_date' => $domain->register_date ?: now(),
                'expiry_date' => $domain->expiry_date ?: now()->addYears(max($domain->years ?: 1, 1)),
                'provision_status' => 'manual',
                'provision_message' => 'Domain tanpa registrar — ditandai aktif secara manual, tidak ada pendaftaran API yang dilakukan.',
            ]);

            return null;
        }

        $registrar = $domain->registrar;
        $client = $order->client;

        // TLD yang mewajibkan data kelayakan (lihat
        // LiquidService::ELIGIBILITY_REQUIRED_TLDS) TIDAK didaftarkan
        // otomatis sampai admin mengisi datanya — kalau dipaksa lanjut
        // tanpa itu, registry aslinya (bukan Liqu.id) akan menolak
        // pendaftarannya, padahal klien sudah bayar. Jeda di sini,
        // bukan gagal diam-diam di tengah proses.
        $tldExt = ltrim($domain->tld?->extension ?? '', '.');

        // Daftar TLD-nya diambil dari SERVICE REGISTRAR YANG DIPAKAI
        // domain ini, bukan dari LiquidService secara langsung.
        //
        // Sebelumnya konstanta milik Liqu.id dipakai untuk SEMUA
        // registrar. Akibatnya domain seperti .asia lewat DNAMA ikut
        // ditahan menunggu "data kelayakan" yang tidak pernah diminta
        // DNAMA -- klien sudah bayar tapi domainnya menggantung sampai
        // admin sadar dan mengisi data yang sebenarnya tidak dibutuhkan.
        $eligibilityTlds = defined(get_class($service) . '::ELIGIBILITY_REQUIRED_TLDS')
            ? constant(get_class($service) . '::ELIGIBILITY_REQUIRED_TLDS')
            : [];

        if (! $domain->is_transfer
            && in_array($tldExt, $eligibilityTlds, true)
            && (blank($domain->eligibility_criteria) || blank($domain->eligibility_extra))
        ) {
            if ($domain->provision_status !== 'needs_eligibility') {
                $domain->update([
                    'provision_status' => 'needs_eligibility',
                    'provision_message' => "Domain .{$tldExt} butuh data kelayakan (eligibility) tambahan dari registry sebelum bisa didaftarkan — admin perlu mengisinya dulu di halaman detail domain ini.",
                ]);

                try {
                    app(\App\Services\Notification\NotificationService::class)->domainNeedsEligibility($domain, $tldExt);
                } catch (Throwable $e) {
                    Log::warning('Gagal kirim notifikasi kelayakan domain: ' . $e->getMessage());
                }
            }

            return ['domain' => $domain->domain_name, 'success' => false, 'message' => 'Menunggu data kelayakan domain diisi admin.'];
        }

        // TLD Indonesia (.co.id, .ac.id, dst) mewajibkan dokumen
        // identitas/legalitas diverifikasi PANDI — di luar API Liqu.id
        // sama sekali, jadi TIDAK bisa dicek/dikirim otomatis. Domain
        // ditahan sampai ada dokumen berstatus "approved" (admin yang
        // menandai, setelah benar-benar diverifikasi & diteruskan ke
        // Liqu.id secara manual).
        if (! $domain->is_transfer
            // Daftar persyaratan dibaca dari database (diatur admin di
            // Pengaturan -> Persyaratan), bukan lagi daftar hardcoded.
            && \App\Models\DocumentRequirement::extensionNeedsDocuments($tldExt)
            // Kelengkapan ditentukan dari status per berkas
            // (progressFor), BUKAN dari kolom documents_verified_at.
            //
            // Dulu satu-satunya cara mengisi kolom itu adalah tombol
            // "Tandai Lengkap" di admin. Tombol itu sudah dihapus karena
            // membingungkan (klien sudah bisa bayar begitu berkas
            // terakhir di-approve, jadi tombolnya terasa percuma) --
            // kalau syaratnya tidak diubah ke sini, domain akan
            // tertahan selamanya di 'needs_documents' walau klien sudah
            // bayar, karena tidak ada lagi yang mengisi kolom itu.
            //
            // documents_verified_at tetap dihormati kalau kebetulan
            // sudah terisi dari data lama.
            && is_null($domain->documents_verified_at)
            && ! \App\Models\DomainDocument::progressFor($domain)['complete']
        ) {
            if ($domain->provision_status !== 'needs_documents') {
                $domain->update([
                    'provision_status' => 'needs_documents',
                    'provision_message' => "Domain .{$tldExt} mewajibkan dokumen identitas/legalitas — menunggu klien upload dan admin verifikasi sebelum bisa didaftarkan.",
                ]);

                try {
                    app(\App\Services\Notification\NotificationService::class)->domainNeedsDocuments($domain, $tldExt);
                } catch (Throwable $e) {
                    Log::warning('Gagal kirim notifikasi dokumen domain: ' . $e->getMessage());
                }
            }

            return ['domain' => $domain->domain_name, 'success' => false, 'message' => 'Menunggu dokumen domain diunggah & diverifikasi.'];
        }

        if (! $registrar || ! $client) {
            $domain->update(['provision_status' => 'failed', 'provision_message' => 'Registrar atau data klien tidak ditemukan.']);

            return ['domain' => $domain->domain_name, 'success' => false, 'message' => 'Registrar atau data klien tidak ditemukan.'];
        }

        // WHOIS butuh alamat lengkap — kalau klien belum mengisi provinsi/
        // kode pos (Fase 7a hanya mewajibkan kota & negara), jangan kirim
        // data setengah ke registrar. Lebih baik gagal jelas daripada
        // registrasi domain dengan alamat palsu/kosong.
        if (blank($client->address) || blank($client->city) || blank($client->state) || blank($client->postal_code) || blank($client->phone)) {
            $message = 'Data alamat klien belum lengkap untuk registrasi WHOIS (alamat/kota/provinsi/kode pos/telepon). Lengkapi di halaman Profil, lalu proses manual.';
            $domain->update(['provision_status' => 'failed', 'provision_message' => $message]);

            return ['domain' => $domain->domain_name, 'success' => false, 'message' => $message];
        }

        [$firstName, $lastName] = $this->splitName($client->name);

        $contact = [
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'address'      => $client->address,
            'city'         => $client->city,
            'state'        => $client->state,
            'postal_code'  => $client->postal_code,
            'country'      => $this->countryCode($client->country),
            'phone'        => $client->phone,
            'email'        => $client->email,
        ];

        $service = DomainRegistrarFactory::make($registrar);

        if ($domain->is_transfer) {
            if (! method_exists($service, 'transferDomain')) {
                $message = 'Registrar domain ini belum mendukung transfer otomatis lewat sistem. Proses manual di panel registrar.';
                $domain->update(['provision_status' => 'failed', 'provision_message' => $message]);

                return ['domain' => $domain->domain_name, 'success' => false, 'message' => $message];
            }

            $result = $service->transferDomain([
                'domain'    => $domain->domain_name,
                'years'     => $domain->years ?: 1,
                'auth_code' => $domain->transfer_auth_code ?: '',
                'whois_privacy' => (bool) $domain->whois_privacy,
                'contact'   => $contact,
            ]);

            // Transfer BEDA dari registrasi baru — sukses di sini cuma
            // berarti PERMINTAANNYA berhasil dikirim, bukan domainnya
            // langsung pindah tangan. Ada persetujuan pemilik lama yang
            // dibutuhkan (email dari registrar lama), biasanya 5-7 hari.
            // Status TIDAK diubah jadi "active" di sini — admin yang
            // memastikan dan mengaktifkan manual setelah transfer benar-
            // benar selesai di sisi Liqu.id.
            $domain->update([
                'provision_status'  => $result['success'] ? 'transfer_pending' : 'failed',
                'provision_message' => $result['success']
                    ? 'Permintaan transfer terkirim ke Liqu.id — menunggu persetujuan pemilik domain di registrar lama (biasanya 5-7 hari). Cek status dan aktifkan manual setelah transfer selesai.'
                    : $result['message'],
            ]);

            return ['domain' => $domain->domain_name, 'success' => $result['success'], 'message' => $result['message']];
        }

        $result = $service->registerDomain([
            'domain' => $domain->domain_name,
            'years'  => $domain->years ?: 1,
            // Diteruskan ke registrar saat registrasi — LiquidService sudah
            // bisa membaca ini sejak awal, hanya belum pernah ada yang
            // benar-benar mengisinya dari alur checkout sampai sekarang.
            'whois_privacy' => (bool) $domain->whois_privacy,
            'contact' => $contact,
            // Cuma terisi kalau TLD-nya memang butuh (lihat gerbang
            // kelayakan di atas) DAN admin sudah mengisinya.
            'eligibility_criteria' => $domain->eligibility_criteria,
            'eligibility_extra' => $domain->eligibility_extra,
            // Nameserver default dari registrar — supaya domain tidak
            // dibiarkan tanpa nameserver sama sekali begitu terdaftar.
            // Nanti ditimpa otomatis kalau klien beli hosting untuk
            // domain yang sama (lihat provisionHosting() di bawah).
            'nameservers' => array_values(array_filter([
                $domain->registrar->default_ns1,
                $domain->registrar->default_ns2,
            ])),
        ]);

        $domain->update([
            'provision_status'  => $result['success'] ? 'registered' : 'failed',
            'provision_message' => $result['message'],
            'status'            => $result['success'] ? 'active' : $domain->status,
            'register_date'     => $result['success'] ? now() : $domain->register_date,
            'expiry_date'       => $result['success'] ? now()->addYears($domain->years ?: 1) : $domain->expiry_date,
            // Nameserver yang tadi dikirim ke registrar disimpan juga di
            // sini, supaya halaman "Kelola Nameserver" klien langsung
            // menampilkannya, bukan tampil kosong padahal sudah terisi
            // di sisi registrar.
            'nameservers'       => $result['success'] ? array_values(array_filter([
                $domain->registrar->default_ns1,
                $domain->registrar->default_ns2,
            ])) : $domain->nameservers,
            // Klien yang membeli ID Protection sekalian saat checkout juga
            // dapat masa berlaku 1 tahun sendiri — sama seperti yang
            // memesannya belakangan lewat tombol di halaman domain.
            'privacy_expires_at' => ($result['success'] && $domain->whois_privacy)
                ? now()->addYear()
                : $domain->privacy_expires_at,
        ]);

        return ['domain' => $domain->domain_name, 'success' => $result['success'], 'message' => $result['message']];
    }

    private function notifyClient(Invoice $invoice, array $hostingCredentials, array $domainResults): void
    {
        $client = $invoice->client;

        if (! $client) {
            return;
        }

        try {
            $client->notify(new OrderProvisioned($hostingCredentials, $domainResults));
        } catch (Throwable $e) {
            // Sama seperti OTP (Fase 6a): email bisa gagal kalau SMTP belum
            // dikonfigurasi. Kredensial tetap TIDAK disimpan di database
            // (hanya sekali dikirim), jadi kalau email gagal, admin perlu
            // reset password akun cPanel manual dan infokan ke klien.
            Log::error('Gagal mengirim email kredensial provisioning: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
        }
    }

    /**
     * Username cPanel: alfanumerik, diawali huruf, maks 8 karakter, unik.
     */
    private function generateUsername(string $domain): string
    {
        $sld = (string) Str::of($domain)->before('.')->lower()->replaceMatches('/[^a-z0-9]/', '');

        if ($sld === '' || ctype_digit($sld[0])) {
            $sld = 'u' . $sld;
        }

        $base = substr($sld, 0, 8) ?: 'ulumora';
        $username = $base;
        $i = 1;

        while (HostingAccount::where('username', $username)->exists()) {
            $suffix = (string) $i++;
            $username = substr($base, 0, 8 - strlen($suffix)) . $suffix;
        }

        return $username;
    }

    private function generatePassword(): string
    {
        return Str::password(14, symbols: false) . 'Aa1!';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0], $parts[1] ?? $parts[0]];
    }

    /**
     * Peta sederhana nama negara -> kode ISO alpha-2 yang dipakai registrar.
     * Client menyimpan nama negara bebas teks (Fase 7a), jadi ini tebakan
     * terbaik untuk kasus umum. Kalau tidak dikenali, default ke ID.
     */
    private function countryCode(?string $country): string
    {
        if (! $country) {
            return 'ID';
        }

        if (strlen($country) === 2) {
            return strtoupper($country);
        }

        $map = [
            'indonesia' => 'ID', 'malaysia' => 'MY', 'singapore' => 'SG', 'singapura' => 'SG',
            'united states' => 'US', 'usa' => 'US', 'amerika serikat' => 'US',
            'united kingdom' => 'GB', 'uk' => 'GB', 'inggris' => 'GB',
            'australia' => 'AU', 'thailand' => 'TH', 'vietnam' => 'VN',
            'philippines' => 'PH', 'filipina' => 'PH', 'india' => 'IN', 'japan' => 'JP', 'jepang' => 'JP',
        ];

        return $map[strtolower(trim($country))] ?? 'ID';
    }

    /**
     * Dipanggil dari hook "invoice lunas" yang sama dengan provisioning
     * order baru — tapi ini untuk kasus yang berbeda: invoice PERPANJANGAN
     * layanan yang sudah aktif, dibuat oleh lumora:generate-renewal-invoices.
     *
     * Tidak ada apa pun yang perlu di-"provision" ulang (akun hosting dan
     * domainnya sudah ada) — yang perlu dilakukan hanya menggeser tanggal
     * jatuh tempo/kedaluwarsa ke siklus berikutnya, dan melepas tanda
     * "invoice perpanjangan sedang menunggu" supaya siklus berikutnya bisa
     * dibuatkan invoice baru lagi nanti.
     */
    /**
     * Dipanggil dari hook "invoice lunas" yang sama — kalau invoice ini
     * ternyata invoice upgrade (bukan invoice biasa/perpanjangan), paket
     * hosting-nya benar-benar diganti sekarang, baru setelah pembayaran
     * dikonfirmasi. Sebelum ini, upgrade baru sebatas "diminta", akun
     * aslinya belum tersentuh sama sekali.
     */
    /**
     * Invoice isi ulang saldo lunas — tambahkan ke saldo klien lewat
     * satu-satunya jalan resmi (Client::adjustBalance), supaya tercatat
     * di buku besar. Tidak ada provisioning apa pun di sini.
     */
    public function processTopupPayment(Invoice $invoice): void
    {
        $client = $invoice->client;

        if (! $client) {
            return;
        }

        $client->adjustBalance(
            (float) $invoice->total,
            'topup',
            "Isi ulang saldo — invoice {$invoice->invoice_number}",
            $invoice,
        );

        try {
            app(\App\Services\Notification\NotificationService::class)->balanceTopupPaid($client, (float) $invoice->total);
        } catch (\Throwable $e) {
            Log::warning('Notifikasi isi ulang saldo gagal: ' . $e->getMessage());
        }

        ActivityLog::record(
            'payment',
            "Isi ulang saldo: {$client->name}",
            'Rp ' . number_format((float) $invoice->total, 0, ',', '.') . " — saldo sekarang Rp " . number_format((float) $client->balance, 0, ',', '.'),
            route('admin.clients.details', $client),
            'success',
            $client->id,
        );
    }

    /**
     * Invoice addon lunas — addon-nya diaktifkan, mulai ikut ditagih di
     * perpanjangan berikutnya (lihat HostingAccount::renewalAmount()
     * yang sudah menjumlahkan addon aktif).
     */
    /**
     * Invoice ID Protection lunas — baru sekarang benar-benar diaktifkan
     * di registrar. Sebelum ini klien bisa mengaktifkannya gratis lewat
     * tombol, padahal tiap aktivasi memotong saldo deposit kita.
     */
    public function processPrivacyPayment(Invoice $invoice): void
    {
        $domain = Domain::where('privacy_invoice_id', $invoice->id)->first();

        if (! $domain || ! $domain->registrar) {
            return;
        }

        $service = DomainRegistrarFactory::make($domain->registrar);

        if (! method_exists($service, 'enablePrivacyProtection')) {
            return;
        }

        $result = $service->enablePrivacyProtection($domain->domain_name);

        // Kalau enable biasa ditolak, coba jalur BELI eksplisit — sebagian
        // TLD mewajibkan itu, bukan aktivasi biasa. Ini alasan
        // buyPrivacyProtection() dibuat dulu tapi belum pernah terpakai.
        if (! $result['success'] && method_exists($service, 'buyPrivacyProtection')) {
            $result = $service->buyPrivacyProtection($domain->domain_name);
        }

        if (! $result['success']) {
            Log::error('Gagal mengaktifkan ID Protection setelah dibayar: ' . $result['message'], [
                'domain_id' => $domain->id,
            ]);

            // Invoice-nya TETAP lunas (klien memang sudah bayar) — yang
            // gagal cuma aktivasi di registrar, jadi admin perlu
            // ditindaklanjuti manual, bukan uangnya dianggap hangus.
            try {
                app(\App\Services\Notification\NotificationService::class)->privacyActivationFailed($domain, $result['message']);
            } catch (Throwable $e) {
                Log::warning('Notifikasi gagal aktivasi privacy tidak terkirim: ' . $e->getMessage());
            }

            return;
        }

        // Masa berlaku 1 tahun. Kalau ini PERPANJANGAN (masa lama belum
        // habis), dihitung dari tanggal kedaluwarsa lama — bukan dari
        // hari ini — supaya sisa hari yang sudah dibayar tidak hangus.
        $base = ($domain->privacy_expires_at && $domain->privacy_expires_at->isFuture())
            ? $domain->privacy_expires_at
            : now();

        $domain->update([
            'whois_privacy' => true,
            'privacy_expires_at' => $base->copy()->addYear(),
            'privacy_invoice_id' => null,
        ]);

        ActivityLog::record(
            'domain',
            "ID Protection diaktifkan: {$domain->domain_name}",
            'Setelah pembayaran invoice ' . $invoice->invoice_number,
            route('admin.domains.details', $domain),
            'success',
            $domain->client_id,
        );
    }

    public function processAddonPayment(Invoice $invoice): void
    {
        $addon = \App\Models\HostingAccountAddon::where('invoice_id', $invoice->id)
            ->where('status', 'pending_payment')
            ->first();

        if (! $addon) {
            return;
        }

        $addon->update(['status' => 'active']);

        ActivityLog::record(
            'service',
            "Addon diaktifkan: {$addon->name}",
            $addon->hostingAccount?->domain ?? '—',
            route('admin.hosting-accounts.details', $addon->hosting_account_id),
            'success',
            $addon->hostingAccount?->client_id,
        );
    }

    public function processUpgradePayment(Invoice $invoice): void
    {
        $hosting = HostingAccount::where('pending_upgrade_invoice_id', $invoice->id)->first();

        if (! $hosting || ! $hosting->pendingUpgradeProduct) {
            return;
        }

        $newProduct = $hosting->pendingUpgradeProduct;
        $oldProductName = $hosting->product?->name ?? $hosting->package;

        // Akun otomatis (terhubung server) diganti paketnya sungguhan
        // lewat WHM. Akun manual (tanpa server) cukup dicatat di sistem —
        // admin yang perlu menyesuaikan manual di panel, sama seperti
        // pola provisioning manual di tempat lain.
        if ($hosting->serverModel && $hosting->username && $newProduct->panel_package) {
            try {
                $result = HostingPanelFactory::make($hosting->serverModel)
                    ->changePackage($hosting->username, $newProduct->panel_package);

                if (! $result['success']) {
                    Log::error('Upgrade paket gagal di panel, status database TETAP diperbarui: ' . $result['message'], [
                        'hosting_account_id' => $hosting->id,
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('Upgrade paket error: ' . $e->getMessage(), ['hosting_account_id' => $hosting->id]);
            }
        }

        $hosting->update([
            'product_id' => $newProduct->id,
            'package' => $newProduct->panel_package ?: $newProduct->name,
            'price' => $newProduct->priceForCycle($hosting->billing_cycle),
            'pending_upgrade_product_id' => null,
            'pending_upgrade_invoice_id' => null,
        ]);

        ActivityLog::record(
            'service',
            "Paket diupgrade: {$hosting->domain}",
            "{$oldProductName} → {$newProduct->name}",
            route('admin.hosting-accounts.details', $hosting),
            'success',
            $hosting->client_id,
        );
    }

    public function processRenewalPayment(Invoice $invoice): void
    {
        $hosting = HostingAccount::where('renewal_invoice_id', $invoice->id)->first();

        if ($hosting) {
            $wasSuspended = $hosting->status === 'suspended';

            // Kalau akun sungguhan pernah disuspend lewat API (auto-suspend
            // atau manual), harus dinyalakan lagi lewat API juga — mengubah
            // status di database saja TIDAK membuka akses klien di server
            // yang sebenarnya, akun tetap terkunci di sisi cPanel/WHM.
            if ($wasSuspended && $hosting->serverModel && $hosting->username) {
                try {
                    $result = HostingPanelFactory::make($hosting->serverModel)->unsuspendAccount($hosting->username);

                    if (! $result['success']) {
                        Log::error('Gagal unsuspend otomatis setelah pembayaran: ' . $result['message'], [
                            'hosting_account_id' => $hosting->id,
                        ]);
                    }
                } catch (Throwable $e) {
                    Log::error('Unsuspend otomatis error: ' . $e->getMessage(), ['hosting_account_id' => $hosting->id]);
                }
            }

            $hosting->update([
                'next_due_date' => $hosting->nextCycleDate(),
                'renewal_invoice_id' => null,
                'status' => $wasSuspended ? 'active' : $hosting->status,
            ]);

            ActivityLog::record(
                'service',
                $wasSuspended ? 'Hosting diaktifkan kembali: ' . $hosting->domain : 'Hosting diperpanjang: ' . $hosting->domain,
                'Jatuh tempo baru: ' . $hosting->next_due_date->format('d M Y'),
                route('admin.hosting-accounts.details', $hosting),
                'success',
                $hosting->client_id,
            );
        }

        $domain = Domain::where('renewal_invoice_id', $invoice->id)->first();

        if ($domain) {
            // Tahun ditambahkan dari expiry_date SEBELUMNYA, bukan dari hari
            // ini — supaya domain yang dibayar lebih awal tidak kehilangan
            // sisa masa aktifnya.
            $newExpiry = ($domain->expiry_date ?: now())->copy()->addYear();

            $domain->update([
                'expiry_date' => $newExpiry,
                'renewal_invoice_id' => null,
                'status' => 'active',
            ]);

            // Domain dengan registrar sungguhan idealnya juga memanggil API
            // renew di sini. Belum diimplementasikan — untuk sekarang
            // perpanjangan dicatat di database, dan admin perlu memastikan
            // perpanjangan juga diproses di sisi registrar kalau TLD-nya
            // bukan TLD demo. Ditandai jelas di provision_message supaya
            // tidak diam-diam terlewat.
            if ($domain->registrar_id) {
                $domain->update([
                    'provision_message' => 'Perpanjangan tercatat di sistem pada ' . now()->format('d M Y')
                        . '. Pastikan juga diperpanjang di panel registrar jika belum otomatis.',
                ]);
            }

            ActivityLog::record(
                'domain',
                'Domain diperpanjang: ' . $domain->domain_name,
                'Kedaluwarsa baru: ' . $domain->expiry_date->format('d M Y'),
                route('admin.domains.details', $domain),
                'success',
                $domain->client_id,
            );
        }
    }
}
