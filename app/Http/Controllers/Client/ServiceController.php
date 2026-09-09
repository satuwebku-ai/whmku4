<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Ticket;
use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\Domain\DomainRegistrarFactory;
use App\Services\Hosting\HostingPanelFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ServiceController extends Controller
{
    use AuthorizesClientOwnership;

    public function services(Request $request): View
    {
        return view('client.services.index', $this->servicesData($request));
    }

    public function servicesBootstrap(Request $request): View
    {
        return view('client.services.index', $this->servicesData($request));
    }

    private function servicesData(Request $request): array
    {
        // VPS/cloud punya menu sendiri (client.vps) karena cara
        // mengelolanya beda -- jadi dikecualikan dari daftar ini supaya
        // tidak muncul dobel di dua tempat.
        $cloudServerIds = \App\Models\Server::whereIn('panel', ['idcloudhost'])->pluck('id');

        $services = Auth::guard('client')->user()
            ->hostingAccounts()
            ->where(fn ($q) => $q->whereNotIn('server_id', $cloudServerIds)->orWhereNull('server_id'))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return compact('services');
    }

    public function service(HostingAccount $service): View
    {
        return view('client.services.show', $this->serviceData($service));
    }

    public function serviceBootstrap(HostingAccount $service): View
    {
        return view('client.services.show', $this->serviceData($service));
    }

    private function serviceData(HostingAccount $service): array
    {
        $this->authorizeOwner($service);

        $service->load('orders', 'serverModel');

        $usage = null;
        $sslStatus = null;

        if ($service->status === 'active' && $service->serverModel && $service->username) {
            $panel = HostingPanelFactory::make($service->serverModel);

            if (method_exists($panel, 'getAccountUsage')) {
                $result = $panel->getAccountUsage($service->username);
                $usage = $result['success'] ? $result : null;
            }

            if (method_exists($panel, 'getSslStatus')) {
                try {
                    $result = $panel->getSslStatus($service->domain);
                    $sslStatus = $result['success'] ? $result : null;
                } catch (\Throwable $e) {
                    $sslStatus = null;
                }
            }
        }

        return compact('service', 'usage', 'sslStatus');
    }

    public function domains(Request $request): View
    {
        return view('client.domains.index', $this->domainsData($request));
    }

    public function domainsBootstrap(Request $request): View
    {
        return view('client.domains.index', $this->domainsData($request));
    }

    private function domainsData(Request $request): array
    {
        $domains = Auth::guard('client')->user()
            ->domains()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return compact('domains');
    }

    public function domain(Domain $domain): View
    {
        return view('client.domains.show', $this->domainData($domain));
    }

    public function domainBootstrap(Domain $domain): View
    {
        return view('client.domains.show', $this->domainData($domain));
    }

    private function domainData(Domain $domain): array
    {
        $this->authorizeOwner($domain);

        $lockStatus = null;
        $theftStatus = null;
        $privacyAtRegistrar = null;
        $forwardTo = null;
        $supportsForwarding = false;
        $supportsEmailForwarding = false;

        if ($domain->registrar && $domain->status === 'active') {
            $service = DomainRegistrarFactory::make($domain->registrar);

            if (method_exists($service, 'getDomainLockStatus')) {
                $result = $service->getDomainLockStatus($domain->domain_name);
                $lockStatus = $result['success'] ? $result['locked'] : null;
            }

            if (method_exists($service, 'getTheftProtection')) {
                $result = $service->getTheftProtection($domain->domain_name);
                $theftStatus = $result['success'] ? $result['enabled'] : null;
            }

            if (method_exists($service, 'getPrivacyProtection')) {
                $result = $service->getPrivacyProtection($domain->domain_name);
                $privacyAtRegistrar = $result['success'] ? $result['enabled'] : null;
            }

            if (method_exists($service, 'getDomainForwarding')) {
                $supportsForwarding = true;
                $result = $service->getDomainForwarding($domain->domain_name);
                $forwardTo = $result['success'] ? $result['forward_to'] : null;
            }

            $supportsEmailForwarding = method_exists($service, 'listEmailForwarding');
        }

        return compact(
            'domain', 'lockStatus', 'theftStatus', 'privacyAtRegistrar',
            'forwardTo', 'supportsForwarding', 'supportsEmailForwarding'
        );
    }

    public function domainAddons(Domain $domain): View
    {
        return view('client.domains.addons', $this->domainAddonsData($domain));
    }

    public function domainAddonsBootstrap(Domain $domain): View
    {
        return view('client.domains.addons', $this->domainAddonsData($domain));
    }

    private function domainAddonsData(Domain $domain): array
    {
        $this->authorizeOwner($domain);

        $privacyAtRegistrar = null;

        if ($domain->registrar && $domain->status === 'active') {
            $service = DomainRegistrarFactory::make($domain->registrar);

            if (method_exists($service, 'getPrivacyProtection')) {
                $result = $service->getPrivacyProtection($domain->domain_name);
                $privacyAtRegistrar = $result['success'] ? $result['enabled'] : null;
            }
        }

        return compact('domain', 'privacyAtRegistrar');
    }


    /**
     * Login sekali klik ke cPanel.
     *
     * Server membuat sesi berisi token sekali pakai, lalu klien langsung
     * diarahkan ke sana — tidak perlu tahu password akun cPanel-nya.
     */
    public function changePanelPassword(Request $request, HostingAccount $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        $data = $request->validate([
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        if ($service->status !== 'active') {
            return back()->with('error', 'Layanan ini sedang tidak aktif, jadi belum bisa diubah.');
        }

        if (! $service->serverModel || ! $service->username) {
            return back()->with('error', 'Layanan ini belum terhubung ke server. Silakan hubungi support.');
        }

        $panel = HostingPanelFactory::make($service->serverModel);

        if (! method_exists($panel, 'changePassword')) {
            return back()->with('error', 'Panel ' . $service->serverModel->panel . ' belum mendukung ganti password lewat sini.');
        }

        $result = $panel->changePassword($service->username, $data['new_password']);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Password berhasil diubah.' : 'Gagal mengubah password: ' . $result['message']
        );
    }

    public function loginPanel(Request $request, HostingAccount $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        if ($service->status !== 'active') {
            return back()->with('error', 'Layanan ini sedang tidak aktif, jadi belum bisa diakses.');
        }

        if (! $service->serverModel || ! $service->username) {
            return back()->with('error', 'Layanan ini belum terhubung ke server. Silakan hubungi support.');
        }

        $panel = HostingPanelFactory::make($service->serverModel);

        if (! method_exists($panel, 'createSsoSession')) {
            return back()->with('error', 'Panel ' . $service->serverModel->panel . ' belum mendukung login sekali klik.');
        }

        // ?path=frontend/jupiter/filemanager/index.html dst -- loncat
        // langsung ke fitur tertentu di dalam cPanel (path relatif
        // sungguhan, bukan kode "app" yang ternyata tidak konsisten
        // bekerja di server ini). Daftar path yang valid dijaga di sisi
        // tampilan (client.services.show).
        $path = $request->query('path');

        $result = $panel->createSsoSession($service->username, 'cpaneld', $path);

        if (! $result['success']) {
            return back()->with('error', 'Gagal membuat sesi login: ' . $result['message']);
        }

        // away() dipakai karena tujuannya di luar aplikasi ini.
        return redirect()->away($result['url']);
    }

    /**
     * Ubah nameserver domain lewat API registrar.
     */
    public function updateNameservers(Request $request, Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        $data = $request->validate([
            'nameservers'   => ['required', 'array', 'min:2', 'max:5'],
            'nameservers.*' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/'],
        ], [
            'nameservers.min' => 'Minimal dua nameserver harus diisi.',
            'nameservers.*.regex' => 'Format nameserver tidak valid. Contoh: ns1.contoh.com',
        ]);

        // Buang baris kosong, lalu pastikan tetap ada minimal dua.
        $nameservers = array_values(array_filter(
            array_map('trim', $data['nameservers']),
            fn ($ns) => $ns !== ''
        ));

        if (count($nameservers) < 2) {
            return back()->with('error', 'Minimal dua nameserver harus diisi.');
        }

        if ($domain->status !== 'active') {
            return back()->with('error', 'Nameserver hanya bisa diubah untuk domain yang aktif.');
        }

        if (! $domain->registrar) {
            return back()->with('error', 'Domain ini tidak terhubung ke registrar. Silakan hubungi support.');
        }

        $result = DomainRegistrarFactory::make($domain->registrar)
            ->setNameservers($domain->domain_name, $nameservers);

        if (! $result['success']) {
            return back()->with('error', 'Gagal mengubah nameserver: ' . $result['message']);
        }

        $domain->update(['nameservers' => $nameservers]);

        return back()->with('success', 'Nameserver berhasil diperbarui. Perubahan DNS bisa memakan waktu hingga 24 jam untuk menyebar.');
    }

    /**
     * Klien menyalakan/mematikan perpanjangan otomatis domainnya sendiri.
     * Sebelumnya kolom ini hanya bisa dilihat, tidak bisa diubah klien —
     * satu-satunya jalan adalah menghubungi support, padahal ini murni
     * preferensi klien sendiri, tidak ada alasan untuk melibatkan admin.
     */
    public function toggleDomainAutoRenew(Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        $domain->update(['auto_renew' => ! $domain->auto_renew]);

        return back()->with('success', $domain->auto_renew
            ? 'Perpanjangan otomatis diaktifkan. Invoice akan dibuat otomatis mendekati tanggal kedaluwarsa.'
            : 'Perpanjangan otomatis dimatikan. Anda perlu memperpanjang domain secara manual sebelum kedaluwarsa.');
    }

    /**
     * Nyalakan/matikan ID Protection setelah domain aktif — bukan hanya
     * bisa dipilih sekali di awal saat checkout.
     *
     * Method ini spesifik Liqu.id (belum tentu didukung registrar lain),
     * jadi dicek lewat method_exists sebelum dipanggil — sama seperti pola
     * yang dipakai untuk fitur QRIS tertanam di Duitku.
     */
    /**
     * Nyalakan/matikan ID Protection.
     *
     * MENGAKTIFKAN berbayar — tiap aktivasi memotong saldo deposit kita
     * di registrar, jadi harus dibayar klien dulu (invoice dibuat di
     * sini, aktivasi sungguhan terjadi setelah lunas — lihat
     * ProvisioningService::processPrivacyPayment()).
     *
     * MEMATIKAN gratis & langsung — tidak masuk akal menagih klien untuk
     * berhenti memakai sesuatu, dan mematikan tidak menimbulkan biaya
     * apa pun di sisi kita.
     */
    public function togglePrivacyProtection(Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        if (! $domain->registrar) {
            return back()->with('error', 'Domain ini tidak terhubung ke registrar. Silakan hubungi support.');
        }

        $service = DomainRegistrarFactory::make($domain->registrar);

        // ── Mematikan: langsung, gratis ──
        if ($domain->hasActivePrivacy()) {
            if (! method_exists($service, 'disablePrivacyProtection')) {
                return back()->with('error', 'Registrar domain ini belum mendukung pengaturan ID Protection lewat sistem.');
            }

            $result = $service->disablePrivacyProtection($domain->domain_name);

            if (! $result['success']) {
                return back()->with('error', 'Gagal mematikan ID Protection: ' . $result['message']);
            }

            // privacy_expires_at TIDAK dikosongkan — kalau klien
            // menyalakannya lagi sebelum tanggal itu lewat, sisa masa
            // yang sudah dibayar masih dihormati (lihat
            // processPrivacyPayment yang memperpanjang dari tanggal lama).
            $domain->update(['whois_privacy' => false]);

            return back()->with('success', 'ID Protection dimatikan.');
        }

        // ── Mengaktifkan / memperpanjang: harus bayar dulu ──
        if (! method_exists($service, 'enablePrivacyProtection')) {
            return back()->with('error', 'Registrar domain ini belum mendukung pengaturan ID Protection lewat sistem.');
        }

        if ($domain->privacy_invoice_id) {
            return redirect()->route('client.invoices.show', $domain->privacy_invoice_id)
                ->with('error', 'Sudah ada invoice ID Protection yang menunggu dibayar.');
        }

        $price = (float) \App\Models\Setting::get('whois_privacy_price', 0);

        if ($price <= 0) {
            return back()->with('error', 'Harga ID Protection belum diatur. Silakan hubungi support.');
        }

        $invoice = \App\Models\Invoice::create([
            'client_id' => $domain->client_id,
            'amount' => $price,
            'tax' => 0,
            'discount' => 0,
            'status' => 'unpaid',
            'issue_date' => now(),
            'due_date' => now()->addDays(3),
        ]);

        $isRenewal = $domain->privacy_expires_at !== null;

        \App\Models\InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => ($isRenewal ? 'Perpanjangan ID Protection' : 'ID Protection')
                . " — {$domain->domain_name} (1 tahun)",
            'amount' => $price,
        ]);

        $domain->update(['privacy_invoice_id' => $invoice->id]);

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', 'Invoice ID Protection dibuat — Rp ' . number_format($price, 0, ',', '.') . '. Aktif otomatis setelah dibayar.');
    }

    /**
     * Nyalakan/matikan Registrar Lock — mengunci domain dari transfer
     * tanpa sepengetahuan pemilik. Endpoint ini sempat dikira tidak ada
     * di API Liqu.id sampai ditemukan lewat spesifikasi resmi mereka
     * (/domains/{domain_id}/locked).
     */
    public function toggleDomainLock(Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        if (! $domain->registrar) {
            return back()->with('error', 'Domain ini tidak terhubung ke registrar. Silakan hubungi support.');
        }

        $service = DomainRegistrarFactory::make($domain->registrar);

        if (! method_exists($service, 'lockDomain')) {
            return back()->with('error', 'Registrar domain ini belum mendukung Registrar Lock lewat sistem.');
        }

        // Status sekarang diambil langsung dari registrar (bukan disimpan
        // di database kita) — ini satu-satunya sumber kebenaran, supaya
        // tidak ada kondisi "menurut sistem kita aktif, tapi sebenarnya
        // di registrar tidak".
        $status = $service->getDomainLockStatus($domain->domain_name);
        $turnOn = ! ($status['locked'] ?? false);

        $result = $turnOn
            ? $service->lockDomain($domain->domain_name, 'Dikunci oleh klien lewat panel.')
            : $service->unlockDomain($domain->domain_name);

        // Kalau ternyata status sungguhan di registrar SUDAH sesuai yang
        // diminta (registrar menolak dengan pesan "already locked/
        // unlocked"), itu bukan kegagalan — cuma tanda pembacaan status
        // di atas sempat tidak akurat. Tetap dianggap berhasil.
        $alreadyCorrect = ! $result['success'] && $this->isAlreadyCorrectError($result['message']);

        if (! $result['success'] && ! $alreadyCorrect) {
            return back()->with('error', 'Gagal mengubah Registrar Lock: ' . $result['message']);
        }

        return back()->with('success', $turnOn
            ? 'Registrar Lock diaktifkan — domain tidak bisa dipindah ke registrar lain sampai dimatikan.'
            : 'Registrar Lock dimatikan. Domain sekarang bisa ditransfer.');
    }

    /**
     * Beberapa registrar (dikonfirmasi: Liqu.id) menolak permintaan yang
     * "tidak mengubah apa-apa" dengan pesan error, bukan dianggap sukses
     * tanpa efek — dipakai di beberapa aksi toggle (nameserver, lock,
     * theft protection) supaya semuanya konsisten menanganinya, alih-alih
     * menampilkan "gagal" padahal kondisi yang diinginkan sudah tercapai.
     */
    private function isAlreadyCorrectError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'same value')
            || str_contains($message, 'already locked')
            || str_contains($message, 'already enabled')
            || str_contains($message, 'already disabled')
            || str_contains($message, 'already unlocked');
    }

    /**
     * Klien tidak lagi dapat kode transfer langsung -- diganti jadi
     * mengajukan tiket, admin yang meninjau & menyetujui pengiriman kode
     * ke email klien. Kode EPP setara password sekali pakai untuk
     * transfer domain, jadi sengaja diberi lapisan tinjauan manusia
     * sebelum dikirim, bukan swalayan penuh.
     */
    public function requestAuthCode(Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        // Penjagaan di server, bukan cuma tombolnya disembunyikan di
        // tampilan -- klien bisa saja kirim request langsung ke route
        // ini tanpa lewat tombol. Kode transfer cuma masuk akal untuk
        // domain yang SUDAH terdaftar; domain 'pending' belum punya
        // apa pun di registrar untuk ditransfer.
        if ($domain->provision_status !== 'registered') {
            return back()->with('error', 'Domain ini belum terdaftar, jadi belum bisa diajukan permintaan kode transfer.');
        }

        // Cegah tiket dobel kalau klien klik berkali-kali sebelum admin
        // sempat memproses yang pertama.
        $existing = Ticket::where('domain_id', $domain->id)
            ->where('client_id', $domain->client_id)
            ->where('subject', 'like', 'Permintaan Kode Transfer%')
            ->whereIn('status', ['open', 'answered', 'customer_reply'])
            ->first();

        if ($existing) {
            return redirect()->route('client.tickets.show', $existing)
                ->with('success', 'Permintaan kamu sebelumnya masih diproses tim kami — lihat tiket ini untuk statusnya.');
        }

        $ticket = Ticket::create([
            'client_id'  => $domain->client_id,
            'subject'    => "Permintaan Kode Transfer (EPP) — {$domain->domain_name}",
            'department' => 'support',
            'priority'   => 'medium',
            'domain_id'  => $domain->id,
            'status'     => 'open',
        ]);

        $ticket->replies()->create([
            'client_id' => $domain->client_id,
            'message'   => "Saya minta kode transfer (EPP/Auth Code) untuk domain {$domain->domain_name}, untuk keperluan pemindahan ke registrar lain.",
        ]);

        return redirect()->route('client.tickets.show', $ticket)
            ->with('success', 'Permintaan kode transfer sudah diajukan. Tim kami akan meninjau dan mengirim kodenya ke email kamu setelah disetujui.');
    }

    // ── DNS Management ──────────────────────────────────────────────

    public function dns(Domain $domain): View|RedirectResponse
    {
        return $this->dnsView($domain, 'client.domains.dns', 'client.domains.show');
    }

    public function dnsBootstrap(Domain $domain): View|RedirectResponse
    {
        return $this->dnsView($domain, 'client.domains.dns', 'client.domains.show');
    }

    private function dnsView(Domain $domain, string $view, string $backRoute): View|RedirectResponse
    {
        $this->authorizeOwner($domain);

        // Sama seperti requestAuthCode(): penjagaan di server, bukan
        // cuma tombolnya disembunyikan. Domain yang belum terdaftar
        // tidak punya DNS apa pun di registrar untuk dibaca/diubah --
        // memanggil listDnsRecords() untuk domain seperti ini paling
        // banter cuma error dari API registrar, atau lebih buruk,
        // "berhasil" membaca DNS domain LAIN yang kebetulan pernah
        // memakai nama yang sama sebelum kedaluwarsa.
        if ($domain->provision_status !== 'registered') {
            return redirect()->route($backRoute, $domain)
                ->with('error', 'Domain ini belum terdaftar, DNS belum bisa dikelola.');
        }

        if (! $domain->registrar) {
            return redirect()->route($backRoute, $domain)
                ->with('error', 'Domain ini tidak terhubung ke registrar, DNS tidak bisa dikelola dari sini.');
        }

        $service = DomainRegistrarFactory::make($domain->registrar);

        if (! method_exists($service, 'listDnsRecords')) {
            return redirect()->route($backRoute, $domain)
                ->with('error', 'Registrar domain ini belum mendukung manajemen DNS lewat sistem.');
        }

        $result = $service->listDnsRecords($domain->domain_name);

        return view($view, [
            'domain' => $domain,
            'records' => $result['records'],
            'warning' => $result['success'] ? null : $result['message'],
            'types' => array_keys(\App\Services\Domain\LiquidService::DNS_TYPES),
        ]);
    }

    public function addDnsRecord(Request $request, Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        $data = $request->validate([
            'type'     => ['required', 'in:A,AAAA,CNAME,MX,TXT'],
            'hostname' => ['required', 'string', 'max:255'],
            'value'    => ['required', 'string', 'max:500'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $service = DomainRegistrarFactory::make($domain->registrar);

        if (! method_exists($service, 'addDnsRecord')) {
            return back()->with('error', 'Registrar domain ini belum mendukung manajemen DNS.');
        }

        $result = $service->addDnsRecord(
            $domain->domain_name,
            $data['type'],
            $data['hostname'],
            $data['value'],
            $data['priority'] ?? null,
        );

        return back()->with($result['success'] ? 'success' : 'error',
            $result['success'] ? 'Record DNS berhasil ditambahkan.' : 'Gagal menambah record: ' . $result['message']);
    }

    public function deleteDnsRecord(Request $request, Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        $data = $request->validate([
            'type'     => ['required', 'in:A,AAAA,CNAME,MX,TXT'],
            'hostname' => ['required', 'string'],
            'value'    => ['required', 'string'],
        ]);

        $service = DomainRegistrarFactory::make($domain->registrar);

        if (! method_exists($service, 'deleteDnsRecord')) {
            return back()->with('error', 'Registrar domain ini belum mendukung manajemen DNS.');
        }

        $result = $service->deleteDnsRecord($domain->domain_name, $data['type'], $data['hostname'], $data['value']);

        return back()->with($result['success'] ? 'success' : 'error',
            $result['success'] ? 'Record DNS berhasil dihapus.' : 'Gagal menghapus record: ' . $result['message']);
    }

    /**
     * Ajukan pembatalan layanan — belum menghentikan apapun, hanya masuk
     * antrean tinjauan admin. Ini disengaja: pembatalan otomatis berisiko
     * mematikan layanan yang masih dibutuhkan hanya karena klik yang salah
     * atau permintaan yang berubah pikiran.
     */
    public function requestCancellation(Request $request, HostingAccount $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        if ($service->hasPendingCancellation()) {
            return back()->with('error', 'Sudah ada pengajuan pembatalan yang sedang ditinjau untuk layanan ini.');
        }

        if ($service->status === 'terminated') {
            return back()->with('error', 'Layanan ini sudah tidak aktif.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $service->update([
            'cancellation_status' => 'requested',
            'cancellation_reason' => $data['reason'],
            'cancellation_requested_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan pembatalan berhasil dikirim. Tim kami akan meninjau dalam 1x24 jam.');
    }

    /**
     * Batalkan pengajuan pembatalan yang belum diproses admin.
     */
    public function withdrawCancellation(HostingAccount $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        if (! $service->hasPendingCancellation()) {
            return back()->with('error', 'Tidak ada pengajuan pembatalan yang aktif.');
        }

        $service->update([
            'cancellation_status' => 'none',
            'cancellation_reason' => null,
            'cancellation_requested_at' => null,
        ]);

        return back()->with('success', 'Pengajuan pembatalan dibatalkan.');
    }

    // ── Upgrade Paket Mandiri ──────────────────────────────────────

    /**
     * Halaman pilih paket tujuan upgrade.
     */
    public function upgradeForm(HostingAccount $service): View|RedirectResponse
    {
        $result = $this->upgradeFormData($service, 'client.services.show');
        if ($result instanceof RedirectResponse) return $result;

        return view('client.services.upgrade', $result);
    }

    public function upgradeFormBootstrap(HostingAccount $service): View|RedirectResponse
    {
        $result = $this->upgradeFormData($service, 'client.services.show');
        if ($result instanceof RedirectResponse) return $result;

        return view('client.services.upgrade', $result);
    }

    private function upgradeFormData(HostingAccount $service, string $backRoute): View|RedirectResponse|array
    {
        $this->authorizeOwner($service);

        if ($service->status !== 'active') {
            return redirect()->route($backRoute, $service)
                ->with('error', 'Upgrade hanya bisa dilakukan untuk layanan yang sedang aktif.');
        }

        if ($service->pending_upgrade_invoice_id) {
            return redirect()->route($backRoute, $service)
                ->with('error', 'Sudah ada permintaan upgrade yang menunggu pembayaran. Selesaikan itu dulu, atau hubungi support untuk membatalkannya.');
        }

        $options = $service->upgradeEligibleProducts();

        return [
            'service' => $service,
            'options' => $options,
        ];
    }

    /**
     * Klien memilih paket tujuan — dibuatkan invoice prorata, BELUM
     * benar-benar upgrade. Upgrade sungguhan baru terjadi otomatis
     * setelah invoice ini lunas — lihat
     * ProvisioningService::processUpgradePayment().
     */
    public function requestUpgrade(Request $request, HostingAccount $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $eligible = $service->upgradeEligibleProducts();
        $newProduct = $eligible->firstWhere('id', (int) $data['product_id']);

        if (! $newProduct) {
            return back()->with('error', 'Paket yang dipilih tidak tersedia untuk upgrade dari paket Anda saat ini.');
        }

        $amount = $service->prorateUpgrade($newProduct);

        if ($amount <= 0) {
            return back()->with('error', 'Terjadi kesalahan menghitung biaya upgrade. Silakan hubungi support.');
        }

        $invoice = Invoice::create([
            'client_id' => $service->client_id,
            'amount' => $amount,
            'tax' => 0,
            'discount' => 0,
            'status' => 'unpaid',
            'issue_date' => now(),
            'due_date' => now()->addDays(3),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => "Upgrade {$service->domain}: {$service->product?->name} → {$newProduct->name} (prorata sisa siklus)",
            'amount' => $amount,
        ]);

        $service->update([
            'pending_upgrade_product_id' => $newProduct->id,
            'pending_upgrade_invoice_id' => $invoice->id,
        ]);

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', "Invoice upgrade dibuat — Rp " . number_format($amount, 0, ',', '.') . ". Paket akan diganti otomatis setelah dibayar.");
    }

    /**
     * Batalkan permintaan upgrade yang belum dibayar.
     */
    public function cancelUpgrade(HostingAccount $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        if (! $service->pending_upgrade_invoice_id) {
            return back()->with('error', 'Tidak ada permintaan upgrade yang aktif.');
        }

        // Invoice-nya ikut dibatalkan supaya tidak menggantung sebagai
        // tagihan yatim yang tidak akan pernah diproses.
        $service->pendingUpgradeInvoice?->update(['status' => 'cancelled']);

        $service->update([
            'pending_upgrade_product_id' => null,
            'pending_upgrade_invoice_id' => null,
        ]);

        return back()->with('success', 'Permintaan upgrade dibatalkan.');
    }

    // ── Perpanjang Sekarang ──────────────────────────────────────────

    /**
     * Klien minta invoice perpanjangan dibuat sekarang, tidak menunggu
     * jadwal otomatis H-7. Dipakai baik dari halaman Layanan maupun
     * Domain — dua method terpisah karena tipe modelnya beda, tapi
     * logikanya sama-sama tinggal panggil createRenewalInvoice() yang
     * sudah dipakai bersama perintah terjadwal.
     */
    public function renewServiceNow(HostingAccount $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        if ($service->status !== 'active') {
            return back()->with('error', 'Hanya layanan aktif yang bisa diperpanjang.');
        }

        if ($service->renewal_invoice_id) {
            return redirect()->route('client.invoices.show', $service->renewal_invoice_id)
                ->with('error', 'Sudah ada invoice perpanjangan yang menunggu dibayar.');
        }

        $invoice = $service->createRenewalInvoice();

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', 'Invoice perpanjangan dibuat. Masa aktif diperpanjang otomatis setelah dibayar.');
    }

    public function renewDomainNow(Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        if ($domain->status !== 'active') {
            return back()->with('error', 'Hanya domain aktif yang bisa diperpanjang.');
        }

        if ($domain->renewal_invoice_id) {
            return redirect()->route('client.invoices.show', $domain->renewal_invoice_id)
                ->with('error', 'Sudah ada invoice perpanjangan yang menunggu dibayar.');
        }

        $invoice = $domain->createRenewalInvoice();

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', 'Invoice perpanjangan dibuat. Masa aktif diperpanjang otomatis setelah dibayar.');
    }

    // ── Domain Forwarding ───────────────────────────────────────────

    public function updateDomainForwarding(Request $request, Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        $data = $request->validate([
            'forward_to' => ['nullable', 'url', 'max:500'],
        ], [
            'forward_to.url' => 'Isi alamat lengkap, contoh: https://contoh.com',
        ]);

        if (! $domain->registrar) {
            return back()->with('error', 'Domain ini tidak terhubung ke registrar.');
        }

        $service = DomainRegistrarFactory::make($domain->registrar);

        if (! method_exists($service, 'updateDomainForwarding')) {
            return back()->with('error', 'Registrar domain ini belum mendukung Domain Forwarding.');
        }

        // String kosong = cara resmi mematikan forwarding (tidak ada
        // endpoint DELETE terpisah untuk fitur ini).
        $result = $service->updateDomainForwarding($domain->domain_name, $data['forward_to'] ?? '');

        return back()->with($result['success'] ? 'success' : 'error',
            $result['success']
                ? (filled($data['forward_to'] ?? null) ? 'Domain forwarding diaktifkan.' : 'Domain forwarding dimatikan.')
                : 'Gagal mengubah domain forwarding: ' . $result['message']);
    }

    // ── Theft Protection ────────────────────────────────────────────

    public function toggleTheftProtection(Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        if (! $domain->registrar) {
            return back()->with('error', 'Domain ini tidak terhubung ke registrar.');
        }

        $service = DomainRegistrarFactory::make($domain->registrar);

        if (! method_exists($service, 'getTheftProtection')) {
            return back()->with('error', 'Registrar domain ini belum mendukung Theft Protection.');
        }

        // Status diambil langsung dari registrar (bukan disimpan lokal),
        // sama seperti pola Registrar Lock — satu-satunya sumber
        // kebenaran.
        $status = $service->getTheftProtection($domain->domain_name);
        $turnOn = ! ($status['enabled'] ?? false);

        $result = $turnOn ? $service->enableTheftProtection($domain->domain_name) : $service->disableTheftProtection($domain->domain_name);

        $alreadyCorrect = ! $result['success'] && $this->isAlreadyCorrectError($result['message']);

        return back()->with(($result['success'] || $alreadyCorrect) ? 'success' : 'error',
            ($result['success'] || $alreadyCorrect)
                ? ($turnOn ? 'Theft Protection diaktifkan.' : 'Theft Protection dimatikan.')
                : 'Gagal mengubah Theft Protection: ' . $result['message']);
    }

    // ── Email Forwarding ────────────────────────────────────────────

    public function emailForwarding(Domain $domain): View|RedirectResponse
    {
        return $this->emailForwardingView($domain, 'client.domains.email-forwarding', 'client.domains.show');
    }

    public function emailForwardingBootstrap(Domain $domain): View|RedirectResponse
    {
        return $this->emailForwardingView($domain, 'client.domains.email-forwarding', 'client.domains.show');
    }

    private function emailForwardingView(Domain $domain, string $view, string $backRoute): View|RedirectResponse
    {
        $this->authorizeOwner($domain);

        if (! $domain->registrar) {
            return redirect()->route($backRoute, $domain)->with('error', 'Domain ini tidak terhubung ke registrar.');
        }

        $service = DomainRegistrarFactory::make($domain->registrar);

        if (! method_exists($service, 'listEmailForwarding')) {
            return redirect()->route($backRoute, $domain)->with('error', 'Registrar domain ini belum mendukung Email Forwarding.');
        }

        $result = $service->listEmailForwarding($domain->domain_name);

        return view($view, [
            'domain' => $domain,
            'forwards' => $result['forwards'],
            'warning' => $result['success'] ? null : $result['message'],
        ]);
    }

    public function addEmailForwarding(Request $request, Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        $data = $request->validate([
            'email'      => ['required', 'string', 'max:255', 'regex:/^[^@\s]+$/'],
            'forward_to' => ['required', 'email', 'max:255'],
        ], [
            'email.regex' => 'Isi bagian sebelum @ saja, mis. "info" untuk info@' . $domain->domain_name,
        ]);

        $service = DomainRegistrarFactory::make($domain->registrar);

        $fullEmail = $data['email'] . '@' . $domain->domain_name;
        $result = $service->addEmailForwarding($domain->domain_name, $fullEmail, [$data['forward_to']]);

        return back()->with($result['success'] ? 'success' : 'error',
            $result['success'] ? 'Email forwarding berhasil ditambahkan.' : 'Gagal menambah: ' . $result['message']);
    }

    public function deleteEmailForwarding(Request $request, Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        $data = $request->validate(['email' => ['required', 'string']]);

        $service = DomainRegistrarFactory::make($domain->registrar);
        $result = $service->deleteEmailForwarding($domain->domain_name, $data['email']);

        return back()->with($result['success'] ? 'success' : 'error',
            $result['success'] ? 'Email forwarding berhasil dihapus.' : 'Gagal menghapus: ' . $result['message']);
    }

    // ── Dokumen Persyaratan Domain Indonesia ────────────────────────

    public function domainDocuments(Domain $domain): View
    {
        return view('client.domains.documents', $this->domainDocumentsData($domain));
    }

    public function domainDocumentsBootstrap(Domain $domain): View
    {
        return view('client.domains.documents', $this->domainDocumentsData($domain));
    }

    private function domainDocumentsData(Domain $domain): array
    {
        $this->authorizeOwner($domain);

        $domain->load('documents', 'tld');

        $extension = $domain->tld?->extension ?? '';

        // Daftar persyaratan sekarang diambil dari database (diatur admin
        // di Pengaturan -> Persyaratan), bukan lagi dari daftar hardcoded
        // di DomainDocument::requirements().
        $requirements = \App\Models\DocumentRequirement::forExtension($extension);

        // Berkas yang sudah diunggah, dikelompokkan per persyaratan --
        // supaya tiap baris di form tahu status berkasnya sendiri
        // (belum ada / menunggu / disetujui / ditolak).
        $uploaded = $domain->documents->groupBy('document_requirement_id');

        $progress = \App\Models\DomainDocument::progressFor($domain);

        // Invoice yang menunggu dibayar untuk domain ini -- dicari lewat
        // dua jalur yang sama dengan gerbang pembayaran: order (pembelian
        // baru) dan renewal_invoice_id (perpanjangan).
        //
        // Tanpa ini, klien yang berkasnya sudah lengkap tidak punya jalan
        // keluar dari halaman ini selain menebak-nebak sendiri harus ke
        // menu Invoice.
        $invoice = null;

        if ($progress['complete']) {
            $invoice = \App\Models\Invoice::where('status', '!=', 'paid')
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($domain) {
                    if ($domain->renewal_invoice_id) {
                        $q->orWhere('id', $domain->renewal_invoice_id);
                    }

                    if ($domain->order_id) {
                        $q->orWhereHas('items', fn ($i) => $i->where('order_id', $domain->order_id));
                    }
                })
                ->latest('id')
                ->first();
        }

        return [
            'domain' => $domain,
            'tldExt' => ltrim($extension, '.'),
            'requirements' => $requirements,
            'uploaded' => $uploaded,
            'documents' => $domain->documents,
            'progress' => $progress,
            'invoice' => $invoice,
        ];
    }

    public function uploadDomainDocument(Request $request, Domain $domain): RedirectResponse
    {
        $this->authorizeOwner($domain);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:zip,rar,pdf,jpg,jpeg,png', 'max:2048'],
            'document_requirement_id' => ['nullable', 'integer', 'exists:document_requirements,id'],
        ], [
            'file.mimes' => 'Format file harus ZIP, RAR, PDF, JPG, JPEG, atau PNG.',
            'file.max' => 'Ukuran file maksimal 2 MB per file.',
        ]);

        $requirementId = $data['document_requirement_id'] ?? null;

        // Unggah ulang untuk persyaratan yang DITOLAK: berkas lama
        // ditandai 'replaced', bukan dihapus -- riwayatnya tetap ada
        // kalau nanti perlu ditelusuri kenapa ditolak, tapi tidak ikut
        // dihitung lagi sebagai berkas aktif.
        if ($requirementId) {
            $domain->documents()
                ->where('document_requirement_id', $requirementId)
                ->whereIn('status', ['rejected', 'pending'])
                ->update(['status' => 'replaced']);
        }

        $path = $request->file('file')->store('domain-documents', 'local');

        \App\Models\DomainDocument::create([
            'domain_id' => $domain->id,
            'document_requirement_id' => $requirementId,
            'file_path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'status' => 'pending',
        ]);

        return back()->with('success', 'Berkas berhasil diunggah — menunggu diverifikasi tim kami.');
    }

    public function deleteDomainDocument(\App\Models\DomainDocument $document): RedirectResponse
    {
        $this->authorizeOwner($document->domain);

        if ($document->status === 'approved') {
            return back()->with('error', 'Dokumen yang sudah disetujui tidak bisa dihapus.');
        }

        \Illuminate\Support\Facades\Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Dokumen berhasil dihapus.');
    }

    /**
     * File disimpan di disk 'local' (BUKAN 'public') karena isinya bisa
     * berupa dokumen identitas sensitif (KTP, dll) — cuma bisa diakses
     * lewat rute ini yang memverifikasi kepemilikan dulu, tidak pernah
     * dapat URL publik langsung.
     */
    public function domainDocumentFile(\App\Models\DomainDocument $document)
    {
        $this->authorizeOwner($document->domain);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($document->file_path, $document->original_name);
    }

    // ── Addons ───────────────────────────────────────────────────────

    public function addons(HostingAccount $service): View
    {
        return view('client.services.addons', $this->addonsData($service));
    }

    public function addonsBootstrap(HostingAccount $service): View
    {
        return view('client.services.addons', $this->addonsData($service));
    }

    private function addonsData(HostingAccount $service): array
    {
        $this->authorizeOwner($service);

        $service->load('addons.addon');

        $attachedAddonIds = $service->addons->whereIn('status', ['pending_payment', 'active'])->pluck('addon_id');

        $available = \App\Models\Addon::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($addon) => $addon->priceForCycle($service->billing_cycle) !== null)
            ->reject(fn ($addon) => $attachedAddonIds->contains($addon->id));

        return [
            'service' => $service,
            'available' => $available,
            'attached' => $service->addons,
        ];
    }

    public function requestAddon(Request $request, HostingAccount $service): RedirectResponse
    {
        $this->authorizeOwner($service);

        $data = $request->validate([
            'addon_id' => ['required', 'exists:addons,id'],
        ]);

        $addon = \App\Models\Addon::active()->findOrFail($data['addon_id']);
        $price = $addon->priceForCycle($service->billing_cycle);

        if ($price === null) {
            return back()->with('error', 'Addon ini tidak tersedia untuk siklus tagihan layanan Anda.');
        }

        $amount = $service->prorateAddon($addon);

        if ($amount <= 0) {
            return back()->with('error', 'Terjadi kesalahan menghitung biaya addon. Silakan hubungi support.');
        }

        $invoice = \App\Models\Invoice::create([
            'client_id' => $service->client_id,
            'amount' => $amount,
            'tax' => 0,
            'discount' => 0,
            'status' => 'unpaid',
            'issue_date' => now(),
            'due_date' => now()->addDays(3),
        ]);

        \App\Models\InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => "Addon {$addon->name} — {$service->domain} (prorata sisa siklus)",
            'amount' => $amount,
        ]);

        \App\Models\HostingAccountAddon::create([
            'hosting_account_id' => $service->id,
            'addon_id' => $addon->id,
            'name' => $addon->name,
            'price' => $price,
            'status' => 'pending_payment',
            'invoice_id' => $invoice->id,
        ]);

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', "Invoice addon dibuat — Rp " . number_format($amount, 0, ',', '.') . '. Addon aktif otomatis setelah dibayar.');
    }

    public function cancelAddon(\App\Models\HostingAccountAddon $addon): RedirectResponse
    {
        $this->authorizeOwner($addon->hostingAccount);

        if ($addon->status === 'pending_payment') {
            $addon->invoice?->update(['status' => 'cancelled']);
        }

        $addon->update(['status' => 'cancelled']);

        return back()->with('success', "Addon {$addon->name} dihentikan — tidak akan ikut ditagih di perpanjangan berikutnya.");
    }
}
