<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Registrar;
use App\Models\Tld;
use App\Services\Domain\AvailabilityService;
use App\Services\Domain\DomainRegistrarFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DomainController extends Controller
{
    /**
     * Batas jumlah ekstensi yang dicek dalam satu pencarian.
     *
     * Tanpa batas ini, 399 TLD aktif akan dikirim sekaligus — URL jadi
     * terlalu panjang (HTTP 414) dan registrar kena rate limit.
     */
    private const MAX_TLD_PER_SEARCH = 20;

    public function domains(Request $request): View
    {
        return $this->renderList($request, null);
    }

    public function pending(Request $request): View
    {
        return $this->renderList($request, 'pending');
    }

    public function active(Request $request): View
    {
        return $this->renderList($request, 'active');
    }

    public function expired(Request $request): View
    {
        return $this->renderList($request, 'expired');
    }

    public function cancelled(Request $request): View
    {
        return $this->renderList($request, 'cancelled');
    }

    public function domainsBootstrap(Request $request): View
    {
        return view('admin.domains.index', $this->domainListData($request, null));
    }

    public function pendingBootstrap(Request $request): View
    {
        return view('admin.domains.index', $this->domainListData($request, 'pending'));
    }

    public function activeBootstrap(Request $request): View
    {
        return view('admin.domains.index', $this->domainListData($request, 'active'));
    }

    public function expiredBootstrap(Request $request): View
    {
        return view('admin.domains.index', $this->domainListData($request, 'expired'));
    }

    public function cancelledBootstrap(Request $request): View
    {
        return view('admin.domains.index', $this->domainListData($request, 'cancelled'));
    }

    private function renderList(Request $request, ?string $status): View
    {
        return view('admin.domains.index', $this->domainListData($request, $status));
    }

    private function domainListData(Request $request, ?string $status): array
    {
        $domains = Domain::query()
            ->with(['client', 'registrar'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->search, fn ($q) => $q->where('domain_name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return ['domains' => $domains, 'activeStatus' => $status];
    }

    public function details(Domain $domain): View
    {
        return view('admin.domains.details', $this->domainDetailsData($domain));
    }

    public function detailsBootstrap(Domain $domain): View
    {
        return view('admin.domains.details', $this->domainDetailsData($domain));
    }

    private function domainDetailsData(Domain $domain): array
    {
        $domain->load(['client', 'registrar', 'tld', 'order', 'documents']);

        // Status SUNGGUHAN di registrar — dibandingkan dengan catatan
        // kita, supaya ketidakcocokan (mis. masih aktif di Liqu.id
        // padahal klien sudah tidak bayar) langsung kelihatan di sini.
        // Dibungkus try-catch supaya API yang lambat/bermasalah tidak
        // membuat SELURUH halaman detail gagal dimuat.
        $privacyAtRegistrar = null;

        if ($domain->registrar && $domain->status === 'active') {
            try {
                $service = \App\Services\Domain\DomainRegistrarFactory::make($domain->registrar);

                if (method_exists($service, 'getPrivacyProtection')) {
                    $result = $service->getPrivacyProtection($domain->domain_name);
                    $privacyAtRegistrar = $result['success'] ? $result['enabled'] : null;
                }
            } catch (\Throwable $e) {
                $privacyAtRegistrar = null;
            }
        }

        return compact('domain', 'privacyAtRegistrar');
    }

    /**
     * Halaman "Cek Domain" — search ketersediaan domain via API registrar.
     */
    public function search(Request $request, AvailabilityService $checker): View
    {
        $results = null;
        $query = $request->input('domain');

        if ($query) {
            $base = strtolower(preg_replace('/[^a-z0-9\-]/i', '', explode('.', trim($query))[0]));

            // Kalau admin mengetik domain lengkap (mis. "saya.com"),
            // cek ekstensi itu saja — lebih cepat dan lebih relevan.
            $typedExt = str_contains($query, '.') ? '.' . \Illuminate\Support\Str::after($query, '.') : null;

            $tldQuery = Tld::where('is_active', true)->where('register_price', '>', 0);

            if ($typedExt) {
                $tldQuery->where('extension', $typedExt);
            }

            $tlds = $tldQuery->orderBy('register_price')
                ->limit(self::MAX_TLD_PER_SEARCH)
                ->pluck('extension');

            if ($base === '') {
                $results = ['success' => false, 'message' => 'Nama domain tidak valid.', 'results' => [], 'unknown' => []];
            } else {
                $candidates = $tlds->isNotEmpty()
                    ? $tlds->map(fn ($ext) => $base . $ext)->values()->all()
                    : [$query];

                // Memakai RDAP publik, bukan API registrar — bebas rate limit
                // dan tidak menghabiskan kuota reseller untuk sekadar mengecek.
                $results = $checker->check($candidates);
            }
        }

        $tldPrices = Tld::where('is_active', true)->orderBy('extension')->get()->keyBy('extension');

        return view('admin.domains.search', compact('results', 'query', 'tldPrices'));
    }

    public function searchBootstrap(Request $request, AvailabilityService $checker): View
    {
        $results = null;
        $query = $request->input('domain');

        if ($query) {
            $base = strtolower(preg_replace('/[^a-z0-9\-]/i', '', explode('.', trim($query))[0]));
            $typedExt = str_contains($query, '.') ? '.' . \Illuminate\Support\Str::after($query, '.') : null;

            $tldQuery = Tld::where('is_active', true)->where('register_price', '>', 0);

            if ($typedExt) {
                $tldQuery->where('extension', $typedExt);
            }

            $tlds = $tldQuery->orderBy('register_price')
                ->limit(self::MAX_TLD_PER_SEARCH)
                ->pluck('extension');

            if ($base === '') {
                $results = ['success' => false, 'message' => 'Nama domain tidak valid.', 'results' => [], 'unknown' => []];
            } else {
                $candidates = $tlds->isNotEmpty()
                    ? $tlds->map(fn ($ext) => $base . $ext)->values()->all()
                    : [$query];

                $results = $checker->check($candidates);
            }
        }

        $tldPrices = Tld::where('is_active', true)->orderBy('extension')->get()->keyBy('extension');

        return view('admin.domains.search', compact('results', 'query', 'tldPrices'));
    }

    public function create(): View
    {
        $clients = Client::orderBy('name')->get();
        $registrars = Registrar::where('is_active', true)->orderBy('name')->get();
        $tlds = Tld::where('is_active', true)->orderBy('extension')->get();

        return view('admin.domains.form', [
            'domain' => new Domain(),
            'clients' => $clients,
            'registrars' => $registrars,
            'tlds' => $tlds,
        ]);
    }

    public function createBootstrap(): View
    {
        $clients = Client::orderBy('name')->get();
        $registrars = Registrar::where('is_active', true)->orderBy('name')->get();
        $tlds = Tld::where('is_active', true)->orderBy('extension')->get();

        return view('admin.domains.form', [
            'domain' => new Domain(),
            'clients' => $clients,
            'registrars' => $registrars,
            'tlds' => $tlds,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['auto_renew'] = $request->boolean('auto_renew', true);
        $data['whois_privacy'] = $request->boolean('whois_privacy');
        $registerNow = $request->boolean('register_now');

        $data['provision_status'] = 'manual';
        $data['provision_message'] = null;

        if ($registerNow) {
            if (! $data['registrar_id']) {
                return back()->withInput()->with('error', 'Pilih registrar untuk registrasi otomatis.');
            }

            $registrar = Registrar::findOrFail($data['registrar_id']);

            $contact = $request->validate([
                'contact_first_name' => ['required', 'string', 'max:100'],
                'contact_last_name'  => ['required', 'string', 'max:100'],
                'contact_address'    => ['required', 'string', 'max:255'],
                'contact_city'       => ['required', 'string', 'max:100'],
                'contact_state'      => ['required', 'string', 'max:100'],
                'contact_postal_code' => ['required', 'string', 'max:20'],
                'contact_country'    => ['required', 'string', 'max:2'],
                'contact_phone'      => ['required', 'string', 'max:30'],
                'contact_email'      => ['required', 'email', 'max:255'],
            ]);

            $result = DomainRegistrarFactory::make($registrar)->registerDomain([
                'domain' => $data['domain_name'],
                'years'  => $data['years'],
                'whois_privacy' => $data['whois_privacy'],
                'contact' => [
                    'first_name' => $contact['contact_first_name'],
                    'last_name' => $contact['contact_last_name'],
                    'address' => $contact['contact_address'],
                    'city' => $contact['contact_city'],
                    'state' => $contact['contact_state'],
                    'postal_code' => $contact['contact_postal_code'],
                    'country' => $contact['contact_country'],
                    'phone' => $contact['contact_phone'],
                    'email' => $contact['contact_email'],
                ],
            ]);

            $data['provision_status'] = $result['success'] ? 'registered' : 'failed';
            $data['provision_message'] = $result['message'];

            if ($result['success']) {
                $data['status'] = 'active';
                $data['register_date'] = now();
                $data['expiry_date'] = now()->addYears((int) $data['years']);
            }

            $domain = Domain::create($data);

            return $result['success']
                ? redirect()->route('admin.domains.details', $domain)->with('success', 'Domain berhasil didaftarkan otomatis lewat registrar.')
                : redirect()->route('admin.domain.edit.page', $domain)->with('error', 'Data tersimpan, tapi registrasi otomatis GAGAL: ' . $result['message']);
        }

        Domain::create($data);

        return redirect()->route('admin.domains')->with('success', 'Domain berhasil dicatat (manual, tanpa registrasi otomatis).');
    }

    public function edit(Domain $domain): View
    {
        $clients = Client::orderBy('name')->get();
        $registrars = Registrar::where('is_active', true)->orderBy('name')->get();
        $tlds = Tld::where('is_active', true)->orderBy('extension')->get();

        return view('admin.domains.form', compact('domain', 'clients', 'registrars', 'tlds'));
    }

    public function editBootstrap(Domain $domain): View
    {
        $clients = Client::orderBy('name')->get();
        $registrars = Registrar::where('is_active', true)->orderBy('name')->get();
        $tlds = Tld::where('is_active', true)->orderBy('extension')->get();

        return view('admin.domains.form', compact('domain', 'clients', 'registrars', 'tlds'));
    }

    public function update(Request $request, Domain $domain): RedirectResponse
    {
        $data = $this->validated($request);
        $data['auto_renew'] = $request->boolean('auto_renew');
        $data['whois_privacy'] = $request->boolean('whois_privacy');

        $domain->update($data);

        return redirect()->route('admin.domains')->with('success', 'Domain berhasil diperbarui.');
    }

    /**
     * Tombol umum "Coba Daftarkan Ulang" — dipakai untuk domain yang
     * gagal karena SEBAB APA PUN (bukan cuma kelayakan/dokumen khusus
     * yang sudah punya jalur sendiri), mis. bug yang sudah diperbaiki
     * di kode, kredensial registrar yang tadinya salah, dll. Tidak
     * mengubah data apa pun, cuma memicu ulang percobaan provisioning.
     */
    public function retryProvisioning(Domain $domain): RedirectResponse
    {
        $order = $domain->order;

        if (! $order) {
            return back()->with('error', 'Order terkait domain ini tidak ditemukan — hubungi developer.');
        }

        $invoiceItem = \App\Models\InvoiceItem::where('order_id', $order->id)->first();

        if (! $invoiceItem) {
            return back()->with('error', 'Invoice terkait domain ini tidak ditemukan — hubungi developer.');
        }

        app(\App\Services\Provisioning\ProvisioningService::class)->provisionInvoice($invoiceItem->invoice);

        $domain->refresh();

        return $domain->provision_status === 'registered'
            ? back()->with('success', 'Domain berhasil didaftarkan.')
            : back()->with('error', 'Masih gagal: ' . $domain->provision_message);
    }

    /**
     * Admin isi data kelayakan untuk TLD yang mewajibkannya (.us, .asia,
     * dst — lihat LiquidService::ELIGIBILITY_REQUIRED_TLDS), lalu
     * pendaftaran domain langsung dicoba ulang saat itu juga.
     */
    public function submitEligibility(Request $request, Domain $domain): RedirectResponse
    {
        $data = $request->validate([
            'eligibility_criteria' => ['required', 'string', 'max:50'],
            'eligibility_extra' => ['required', 'string', 'max:500'],
        ]);

        $domain->update($data);

        $order = $domain->order;

        if (! $order) {
            return back()->with('error', 'Data kelayakan tersimpan, tapi order terkait domain ini tidak ditemukan — hubungi developer.');
        }

        // provisionDomain() itu private (sengaja, dipakai internal), jadi
        // dipicu ulang lewat pintu masuk publiknya: provisionInvoice().
        // Item lain di invoice yang sama (kalau ada) aman ikut diproses
        // ulang — semuanya sudah dijaga idempoten (item yang sudah
        // beres dilewati, tidak diulang).
        $invoiceItem = \App\Models\InvoiceItem::where('order_id', $order->id)->first();

        if (! $invoiceItem) {
            return back()->with('error', 'Data kelayakan tersimpan, tapi invoice terkait tidak ditemukan — hubungi developer.');
        }

        app(\App\Services\Provisioning\ProvisioningService::class)->provisionInvoice($invoiceItem->invoice);

        $domain->refresh();

        return $domain->provision_status === 'registered'
            ? back()->with('success', 'Data kelayakan tersimpan dan domain berhasil didaftarkan.')
            : back()->with('error', 'Data kelayakan tersimpan, tapi pendaftaran masih gagal: ' . $domain->provision_message);
    }

    /**
     * Setujui/tolak satu file dokumen — murni untuk kasih tahu klien
     * mana file yang oke/perlu diunggah ulang, TIDAK otomatis memicu
     * pendaftaran (lihat markDocumentsVerified() untuk itu).
     */
    public function reviewDocument(Request $request, \App\Models\DomainDocument $document): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $document->update($data);

        return back()->with('success', 'Status dokumen diperbarui.');
    }

    /**
     * Kirim dokumen persyaratan domain yang sudah diunggah klien ke
     * registrar/reseller lewat email — untuk registrar yang verifikasi
     * dokumennya (KTP/NPWP dll) masih manual lewat email, bukan API.
     */
    public function sendDocumentsToRegistrar(Request $request, Domain $domain): RedirectResponse
    {
        $data = $request->validate([
            'recipient_email' => ['required', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $domain->load('documents');

        // Dikirim persis berkas yang SEDANG berlaku (bukan versi lama
        // yang sudah diganti klien) -- pola sama dengan
        // DomainDocument::progressFor(), supaya tidak mungkin registrar
        // menerima berkas yang sudah kedaluwarsa.
        $documents = $domain->documents->where('status', '!=', 'replaced')->values();

        if ($documents->isEmpty()) {
            return back()->with('error', 'Belum ada dokumen yang bisa dikirim untuk domain ini.');
        }

        \Illuminate\Support\Facades\Notification::route('mail', $data['recipient_email'])
            ->notify(new \App\Notifications\DomainDocumentsForwarded($domain, $documents, $data['note'] ?? null));

        \App\Models\ActivityLog::record(
            'domain',
            'Dokumen domain dikirim ke registrar',
            "{$domain->domain_name} → {$data['recipient_email']} ({$documents->count()} berkas)",
            route('admin.domains.details', $domain),
            'info',
            $domain->client_id,
        );

        return back()->with('success', "Dokumen berhasil dikirim ke {$data['recipient_email']}.");
    }

    /**
     * Aksi eksplisit admin: "saya sudah tinjau semua dokumen, cukup
     * lengkap, lanjutkan pendaftaran" — bukan disimpulkan otomatis dari
     * status approve/reject per file, karena persyaratan tiap TLD ada
     * yang bersifat "atau" dan ada yang opsional.
     */
    public function verifyDomainDocuments(Domain $domain): RedirectResponse
    {
        // Penjagaan di server -- SEBELUMNYA tombol ini langsung
        // menyetel documents_verified_at tanpa mengecek ulang, cuma
        // mengandalkan tombolnya disembunyikan di tampilan kalau belum
        // lengkap. Kalau halaman sempat basi (belum di-refresh setelah
        // approve/reject terakhir) atau route ini dipanggil langsung,
        // gerbang pembayaran bisa terlewati padahal masih ada berkas
        // yang belum disetujui -- karena documents_verified_at
        // melewati SEMUA pengecekan lain di
        // InvoiceController::documentBlocker().
        $progress = \App\Models\DomainDocument::progressFor($domain);

        if (! $progress['complete']) {
            return back()->with('error', 'Belum semua berkas disetujui — masih ada yang berstatus menunggu/ditolak. Refresh halaman ini untuk melihat status terbaru.');
        }

        $domain->update(['documents_verified_at' => now()]);

        $order = $domain->order;
        $invoiceItem = $order ? \App\Models\InvoiceItem::where('order_id', $order->id)->first() : null;
        $invoice = $invoiceItem?->invoice;

        // DUA SKENARIO BERBEDA, jangan disamakan:
        //
        // 1. Invoice BELUM dibayar (kasus paling umum sekarang, dari
        //    gerbang berkas pra-checkout) -- menandai terverifikasi di
        //    sini cukup MEMBUKA GERBANG PEMBAYARAN (lihat
        //    InvoiceController::documentBlocker, yang mengecek kolom
        //    documents_verified_at ini). Domain BELUM boleh didaftarkan
        //    sekarang -- itu terjadi nanti secara normal lewat alur
        //    pembayaran (Invoice::booted -> provisionInvoice), sama
        //    seperti domain tanpa persyaratan apa pun.
        //
        //    SEBELUMNYA method ini langsung memanggil provisionInvoice()
        //    di sini juga, TIDAK PEDULI status invoice -- artinya klik
        //    tombol ini bisa mendaftarkan domain sungguhan ke registrar
        //    SEBELUM klien bayar sepeser pun.
        //
        // 2. Invoice SUDAH dibayar (kasus lama: klien sudah bayar duluan,
        //    tapi provisioning tertahan status needs_documents sampai
        //    berkasnya ditinjau) -- di sinilah provisionInvoice() memang
        //    HARUS dipanggil langsung, karena pembayaran sudah lama
        //    diterima dan tidak akan pernah memicu ulang dengan
        //    sendirinya.
        if (! $invoice || $invoice->status !== 'paid') {
            return back()->with('success', 'Dokumen ditandai lengkap. Domain akan didaftarkan otomatis setelah klien membayar invoice.');
        }

        app(\App\Services\Provisioning\ProvisioningService::class)->provisionInvoice($invoice);

        $domain->refresh();

        return $domain->provision_status === 'registered'
            ? back()->with('success', 'Dokumen ditandai lengkap dan domain berhasil didaftarkan (invoice sudah lunas sebelumnya).')
            : back()->with('error', 'Dokumen ditandai lengkap, tapi pendaftaran masih gagal: ' . $domain->provision_message);
    }

    /**
     * Admin lihat/unduh file dokumen — sama seperti sisi klien, disk
     * 'local' (bukan publik) karena bisa berisi KTP/dokumen sensitif.
     */
    public function documentFile(\App\Models\DomainDocument $document)
    {
        return \Illuminate\Support\Facades\Storage::disk('local')->response($document->file_path, $document->original_name);
    }

    public function destroy(Domain $domain): RedirectResponse
    {
        $domain->delete();

        return redirect()->route('admin.domains')->with('success', 'Data domain berhasil dihapus (catatan: pendaftaran di registrar TIDAK ikut dibatalkan).');
    }

    /**
     * Transfer domain butuh persetujuan pemilik lama di registrar
     * sebelumnya — tidak ada webhook dari Liqu.id yang memberi tahu kita
     * kapan itu selesai, jadi admin yang memastikan manual (login ke
     * Liqu.id, cek status domainnya "Live"), baru menandai selesai di sini.
     */
    /**
     * Domain yang baru kedaluwarsa biasanya masih bisa dipulihkan lewat
     * "masa tenggang" registri (redemption period) sebelum benar-benar
     * dilepas ke publik — dengan biaya tambahan dari registrar. Jendela
     * waktunya beda-beda tiap TLD, jadi tombol ini bisa saja tetap gagal
     * kalau masa tenggangnya sudah lewat.
     */
    public function restore(Domain $domain): RedirectResponse
    {
        if ($domain->status !== 'expired' || ! $domain->registrar) {
            return back()->with('error', 'Cuma domain berstatus kedaluwarsa dan terhubung registrar yang bisa dipulihkan.');
        }

        $service = \App\Services\Domain\DomainRegistrarFactory::make($domain->registrar);

        if (! method_exists($service, 'restoreDomain')) {
            return back()->with('error', 'Registrar domain ini belum mendukung pemulihan domain lewat sistem.');
        }

        $result = $service->restoreDomain($domain->domain_name);

        if (! $result['success']) {
            return back()->with('error', 'Gagal memulihkan domain: ' . $result['message'] . ' (kemungkinan masa tenggang sudah lewat).');
        }

        $domain->update([
            'status' => 'active',
            'provision_status' => 'registered',
            'provision_message' => 'Dipulihkan dari masa tenggang oleh ' . (auth('admin')->user()->name ?? 'admin'),
        ]);

        \App\Models\ActivityLog::record(
            'domain',
            'Domain dipulihkan dari kedaluwarsa: ' . $domain->domain_name,
            'Dipulihkan manual oleh admin',
            route('admin.domains.details', $domain),
            'success',
            $domain->client_id,
        );

        return back()->with('success', 'Domain berhasil dipulihkan dan diaktifkan kembali.');
    }

    public function markTransferComplete(Domain $domain): RedirectResponse
    {
        if (! $domain->is_transfer || $domain->provision_status !== 'transfer_pending') {
            return back()->with('error', 'Domain ini tidak sedang menunggu konfirmasi transfer.');
        }

        $domain->update([
            'status' => 'active',
            'provision_status' => 'registered',
            'provision_message' => 'Transfer dikonfirmasi selesai secara manual oleh admin.',
            'register_date' => $domain->register_date ?: now(),
            'expiry_date' => $domain->expiry_date ?: now()->addYears(max($domain->years ?: 1, 1)),
        ]);

        \App\Models\ActivityLog::record(
            'domain',
            'Transfer domain dikonfirmasi selesai: ' . $domain->domain_name,
            'Ditandai manual oleh ' . (auth('admin')->user()->name ?? 'admin'),
            route('admin.domains.details', $domain),
            'success',
            $domain->client_id,
        );

        return back()->with('success', 'Transfer domain ditandai selesai dan diaktifkan.');
    }

    public function renew(Domain $domain): RedirectResponse
    {
        if (! $domain->registrar) {
            return back()->with('error', 'Domain ini tidak terhubung ke registrar (manual), tidak bisa diperpanjang otomatis.');
        }

        $result = DomainRegistrarFactory::make($domain->registrar)->renewDomain($domain->domain_name, 1);

        if ($result['success']) {
            $domain->update([
                'expiry_date' => $domain->expiry_date?->addYear() ?? now()->addYear(),
                'provision_status' => 'registered',
                'provision_message' => $result['message'],
            ]);

            return back()->with('success', 'Domain berhasil diperpanjang 1 tahun.');
        }

        $domain->update(['provision_message' => $result['message']]);

        return back()->with('error', 'Gagal memperpanjang domain: ' . $result['message']);
    }

    /**
     * Batalkan domain (ubah status jadi cancelled, tidak menghapus data).
     */
    public function cancel(Request $request): RedirectResponse
    {
        $domain = Domain::findOrFail($request->input('domain_id'));
        $domain->update(['status' => 'cancelled']);

        return back()->with('success', "Domain {$domain->domain_name} dibatalkan.");
    }

    /**
     * Simpan catatan internal staf untuk domain ini.
     */
    public function notes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'domain_id' => ['required', 'exists:domains,id'],
            'internal_notes' => ['nullable', 'string'],
        ]);

        $domain = Domain::findOrFail($data['domain_id']);
        $domain->update(['internal_notes' => $data['internal_notes']]);

        return back()->with('success', 'Catatan berhasil disimpan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id'      => ['required', 'exists:clients,id'],
            'registrar_id'   => ['nullable', 'exists:registrars,id'],
            'tld_id'         => ['nullable', 'exists:tlds,id'],
            'domain_name'    => ['required', 'string', 'max:255'],
            'price'          => ['required', 'numeric', 'min:0'],
            'years'          => ['required', 'integer', 'min:1', 'max:10'],
            'status'         => ['required', 'in:pending,active,expired,cancelled'],
            'expiry_date'    => ['nullable', 'date'],
        ]);
    }

    /**
     * Untuk domain LAMA yang didaftarkan sebelum nameserver default
     * diatur (atau sebelum nameserver bermerek jadi/terverifikasi) —
     * satu klik menerapkan nameserver default registrar sekarang,
     * tanpa perlu klien masuk ke halaman Kelola Nameserver sendiri.
     */
    public function applyDefaultNameservers(Domain $domain): RedirectResponse
    {
        if (! $domain->registrar || blank($domain->registrar->default_ns1) || blank($domain->registrar->default_ns2)) {
            return back()->with('error', 'Registrar domain ini belum punya Nameserver Default yang diatur.');
        }

        $service = \App\Services\Domain\DomainRegistrarFactory::make($domain->registrar);

        $nameservers = array_values(array_filter([
            $domain->registrar->default_ns1,
            $domain->registrar->default_ns2,
        ]));

        $result = $service->setNameservers($domain->domain_name, $nameservers);

        // Liqu.id menolak permintaan kalau nilai yang diminta SAMA dengan
        // yang sudah tersimpan di sana — itu bukan kegagalan sungguhan,
        // cuma tanda catatan KITA yang ketinggalan (nameserver domain ini
        // sebenarnya sudah benar di Liqu.id, kita saja belum tahu).
        $alreadyCorrect = ! $result['success'] && $this->isAlreadyCorrectError($result['message']);

        if (! $result['success'] && ! $alreadyCorrect) {
            return back()->with('error', 'Gagal menerapkan nameserver: ' . $result['message']);
        }

        $domain->update(['nameservers' => $nameservers]);

        return back()->with('success', $alreadyCorrect
            ? 'Nameserver domain ini ternyata sudah benar di Liqu.id — catatan kita disesuaikan.'
            : 'Nameserver default berhasil diterapkan ke domain ini.');
    }

    /**
     * Liqu.id (dan kemungkinan registrar lain) menolak permintaan yang
     * "tidak mengubah apa-apa" dengan pesan error, bukan dianggap sukses
     * tanpa efek — dipakai di beberapa aksi toggle (nameserver, lock,
     * theft protection) supaya semuanya konsisten menangani kasus ini.
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
}
