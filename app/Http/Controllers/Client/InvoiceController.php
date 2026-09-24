<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\Billing\BillingException;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Billing\InvoiceService;
use App\Services\Notification\NotificationService;
use App\Services\Payment\PaymentGatewayFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    use AuthorizesClientOwnership;

    public function invoices(Request $request): View
    {
        return view('client.invoices.index', $this->invoicesData($request));
    }

    public function invoicesBootstrap(Request $request): View
    {
        return view('client.invoices.index', $this->invoicesData($request));
    }

    private function invoicesData(Request $request): array
    {
        $invoices = Auth::guard('client')->user()
            ->invoices()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return compact('invoices');
    }

    public function invoice(Invoice $invoice): View
    {
        return view('client.invoices.show', $this->invoiceData($invoice));
    }

    public function invoiceBootstrap(Invoice $invoice): View
    {
        return view('client.invoices.show', $this->invoiceData($invoice));
    }

    private function invoiceData(Invoice $invoice): array
    {
        $this->authorizeOwner($invoice);

        $invoice->load(['order', 'items.order']);

        $gateways = PaymentGateway::where('is_active', true)->orderBy('sort_order')->get();

        $pendingPayment = Payment::where('invoice_id', $invoice->id)
            ->whereIn('status', ['initiated', 'pending'])
            ->latest()
            ->first();

        return compact('invoice', 'gateways', 'pendingPayment');
    }

    /**
     * Cari domain di invoice ini yang berkas persyaratannya BELUM
     * lengkap/disetujui. Mengembalikan domain pertama yang menghalangi,
     * atau null kalau semuanya beres.
     *
     * Memakai DomainDocument::progressFor() -- sumber yang sama dengan
     * halaman klien & halaman verifikasi admin, jadi tidak mungkin
     * ketiganya berbeda pendapat soal "sudah lengkap belum".
     */
    private function documentBlocker(Invoice $invoice): ?\App\Models\Domain
    {
        $orderIds = $invoice->items()->pluck('order_id')->filter()->unique();

        // Dua jalur, karena domain bisa tersambung ke invoice lewat dua
        // cara berbeda:
        //   - PEMBELIAN BARU  -> lewat order_id di InvoiceItem
        //   - PERPANJANGAN    -> lewat renewal_invoice_id di Domain
        //                        (invoice perpanjangan tidak punya order)
        // Tanpa jalur kedua, perpanjangan domain berpersyaratan bisa
        // dibayar tanpa berkas sama sekali.
        $domains = \App\Models\Domain::with(['tld', 'documents'])
            ->where(function ($q) use ($orderIds, $invoice) {
                if ($orderIds->isNotEmpty()) {
                    $q->whereIn('order_id', $orderIds);
                }

                $q->orWhere('renewal_invoice_id', $invoice->id);
            })
            ->get();

        if ($domains->isEmpty()) {
            return null;
        }

        foreach ($domains as $domain) {
            // Domain yang sudah ditandai terverifikasi admin dilewati --
            // termasuk kasus persyaratan "atau"/opsional yang diputuskan
            // manual oleh admin.
            if ($domain->documents_verified_at) {
                continue;
            }

            if (! \App\Models\DomainDocument::progressFor($domain)['complete']) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * Klien memilih gateway dan memulai pembayaran.
     */
    public function pay(Request $request, Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        $this->authorizeOwner($invoice);

        try {
            $invoices->assertPayable($invoice);
        } catch (BillingException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Gerbang berkas persyaratan: invoice yang memuat domain
        // berpersyaratan TIDAK boleh dibayar sebelum semua berkas
        // wajibnya disetujui admin.
        //
        // Dicegah di sini (sebelum transaksi dibuat), bukan sesudah --
        // kalau klien terlanjur bayar untuk domain yang berkasnya belum
        // lengkap, uangnya sudah masuk sementara domain tidak bisa
        // diproses, dan penyelesaiannya jadi urusan refund manual.
        if ($blocker = $this->documentBlocker($invoice)) {
            return redirect()
                ->route('client.domains.documents', $blocker)
                ->with('error', "Berkas persyaratan untuk {$blocker->domain_name} belum lengkap atau belum disetujui. Lengkapi dulu sebelum melanjutkan pembayaran.");
        }

        $data = $request->validate([
            'payment_gateway_id' => ['required', 'exists:payment_gateways,id'],
        ]);

        $gateway = PaymentGateway::where('is_active', true)->findOrFail($data['payment_gateway_id']);

        // Duitku MEWAJIBKAN klien memilih metode (VA bank mana, e-wallet
        // mana, dst) SEBELUM transaksi dibuat — beda dari Midtrans/Xendit
        // yang punya halaman pilihan sendiri setelah transaksi jadi. Jadi
        // diarahkan dulu ke halaman pemilihan, bukan langsung diproses.
        if ($gateway->driver === 'duitku') {
            return redirect()->route('client.invoices.duitku-methods', [$invoice, 'payment_gateway_id' => $gateway->id]);
        }

        $payment = $this->getOrCreatePendingPayment($invoice, $gateway);

        if ($payment instanceof RedirectResponse) {
            return $payment;
        }

        $result = $this->createGatewayTransactionOnce($payment, $gateway);

        return $this->finalizePaymentAttempt($payment, $gateway, $result);
    }

    /**
     * Daftar metode pembayaran Duitku yang aktif (VA, e-wallet, QRIS, dst)
     * beserta biayanya masing-masing — diambil LANGSUNG dari Duitku setiap
     * kali halaman ini dibuka, supaya selalu sesuai kondisi aktual akun
     * (bukan daftar tetap yang bisa ketinggalan zaman).
     */
    public function duitkuMethods(Request $request, Invoice $invoice): View|RedirectResponse
    {
        return $this->duitkuMethodsView($request, $invoice, 'client.invoices.duitku-methods', 'client.invoices.show');
    }

    public function duitkuMethodsBootstrap(Request $request, Invoice $invoice): View|RedirectResponse
    {
        return $this->duitkuMethodsView($request, $invoice, 'client.invoices.duitku-methods', 'client.invoices.show');
    }

    private function duitkuMethodsView(Request $request, Invoice $invoice, string $view, string $backRoute): View|RedirectResponse
    {
        $this->authorizeOwner($invoice);

        try {
            app(InvoiceService::class)->assertPayable($invoice);
        } catch (BillingException $e) {
            return redirect()->route($backRoute, $invoice)->with('error', $e->getMessage());
        }

        $data = $request->validate(['payment_gateway_id' => ['required', 'exists:payment_gateways,id']]);
        $gateway = PaymentGateway::where('is_active', true)->where('driver', 'duitku')->findOrFail($data['payment_gateway_id']);

        $fee = $gateway->calculateFee((float) $invoice->total);
        $total = (float) $invoice->total + $fee;

        $result = (new \App\Services\Payment\DuitkuService($gateway))->getPaymentMethods($total);

        if (! $result['success']) {
            return redirect()->route($backRoute, $invoice)
                ->with('error', 'Tidak bisa mengambil daftar metode pembayaran Duitku: ' . $result['message']);
        }

        $grouped = collect($result['methods'])->groupBy(fn ($m) => \App\Services\Payment\DuitkuService::methodCategory($m['paymentMethod']));

        return view($view, [
            'invoice' => $invoice,
            'gateway' => $gateway,
            'grouped' => $grouped,
            'total' => $total,
        ]);
    }

    public function payDuitkuMethod(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwner($invoice);

        try {
            app(InvoiceService::class)->assertPayable($invoice);
        } catch (BillingException $e) {
            return back()->with('error', $e->getMessage());
        }

        $data = $request->validate([
            'payment_gateway_id' => ['required', 'exists:payment_gateways,id'],
            'method_code' => ['required', 'string', 'max:2'],
        ]);

        $gateway = PaymentGateway::where('is_active', true)->where('driver', 'duitku')->findOrFail($data['payment_gateway_id']);

        $payment = $this->getOrCreatePendingPayment($invoice, $gateway);

        if ($payment instanceof RedirectResponse) {
            return $payment;
        }

        // Kode metode disimpan di dalam lock yang sama dengan pemanggilan
        // provider. Kalau dua tab mengirim metode berbeda bersamaan, metode
        // yang benar-benar dipakai provider tidak boleh berubah di tengah
        // proses request.
        $result = $this->createGatewayTransactionOnce($payment, $gateway, $data['method_code']);

        return $this->finalizePaymentAttempt($payment, $gateway, $result);
    }

    /**
     * Ambil pembayaran yang masih berjalan untuk invoice + gateway yang
     * sama, atau buat baru — dipakai bersama oleh pay() biasa dan alur
     * pemilihan metode Duitku, supaya logikanya tidak dobel dua tempat.
     *
     * @return Payment|RedirectResponse Payment kalau perlu lanjut diproses,
     *   atau RedirectResponse kalau pembayaran lama yang masih hidup bisa
     *   langsung dipakai ulang (proses berhenti di sini).
     */
    private function getOrCreatePendingPayment(Invoice $invoice, PaymentGateway $gateway): Payment|RedirectResponse
    {
        return DB::transaction(function () use ($invoice, $gateway) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $amount = (float) $invoice->total;
            $fee = $gateway->calculateFee($amount);

            $payment = Payment::where('invoice_id', $invoice->id)
                ->where('payment_gateway_id', $gateway->id)
                ->whereIn('status', ['initiated', 'pending'])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($payment) {
                $payment->update([
                    'amount' => $amount,
                    'fee'    => $fee,
                    'total'  => $amount + $fee,
                ]);

                if ($payment->payment_url && (! $payment->expires_at || $payment->expires_at->isFuture())) {
                    return redirect()->away($payment->payment_url);
                }

                if ($gateway->isManual()) {
                    return redirect()->route('client.invoices.show', $invoice)
                        ->with('success', 'Silakan lakukan transfer sesuai instruksi di bawah, lalu konfirmasi ke tim kami.');
                }

                return $payment;
            }

            return Payment::create([
                'invoice_id'         => $invoice->id,
                'client_id'          => $invoice->client_id,
                'payment_gateway_id' => $gateway->id,
                'amount'             => $amount,
                'fee'                => $fee,
                'total'              => $amount + $fee,
                'currency'           => $gateway->currency,
                'status'             => 'initiated',
            ]);
        });
    }

    /**
     * Satu pembayaran hanya boleh mempunyai satu inisialisasi provider yang
     * sedang berjalan. lockForUpdate() di getOrCreatePendingPayment() hanya
     * melindungi pembuatan baris database; lock tersebut sudah dilepas ketika
     * HTTP call ke gateway dimulai. Tanpa lock kedua, dua request paralel bisa
     * sama-sama melihat payment yang belum punya external_id lalu membuat dua
     * transaksi gateway.
     *
     * Kunci dibuat berdasarkan invoice + gateway, bukan hanya payment id,
     * supaya alur QRIS dan alur pembayaran biasa juga saling menunggu.
     */
    private function createGatewayTransactionOnce(
        Payment $payment,
        PaymentGateway $gateway,
        ?string $paymentMethod = null,
    ): array {
        $result = Cache::lock(
            "payment-gateway-init:{$payment->invoice_id}:{$gateway->id}",
            120
        )->get(function () use ($payment, $gateway, $paymentMethod) {
            $payment->refresh();

            // Request lain mungkin sudah selesai saat request ini menunggu
            // lock. Gunakan hasil yang sudah tersimpan, jangan memanggil
            // provider untuk kedua kalinya.
            if ($payment->external_id || $payment->payment_url) {
                return [
                    'success' => true,
                    'message' => 'Pembayaran sudah berhasil diinisialisasi.',
                    'payment_url' => $payment->payment_url,
                    'external_id' => $payment->external_id,
                    'raw' => $payment->gateway_response,
                ];
            }

            if ($paymentMethod !== null) {
                $payment->update(['payment_method' => $paymentMethod]);
            }

            return PaymentGatewayFactory::make($gateway)->createTransaction($payment);
        });

        if (is_array($result)) {
            return $result;
        }

        return [
            'success' => false,
            'busy' => true,
            'message' => 'Pembayaran sedang diproses. Silakan coba lagi sebentar.',
            'payment_url' => null,
            'external_id' => null,
            'raw' => null,
        ];
    }

    private function finalizePaymentAttempt(Payment $payment, PaymentGateway $gateway, array $result): RedirectResponse
    {
        if (! $result['success']) {
            if ($result['busy'] ?? false) {
                return back()->with('error', $result['message']);
            }

            $payment->update(['status' => 'failed', 'gateway_response' => ['error' => $result['message']]]);

            return back()->with('error', 'Gagal memulai pembayaran: ' . $result['message']);
        }

        $payment->update([
            'payment_url'      => $result['payment_url'],
            'external_id'      => $result['external_id'],
            'gateway_response' => $result['raw'],
        ]);

        if ($result['payment_url']) {
            return redirect()->away($result['payment_url']);
        }

        return back()->with('success', 'Silakan lakukan transfer sesuai instruksi di bawah, lalu konfirmasi ke tim kami.');
    }

    /**
     * Tampilkan kode QRIS langsung di halaman kita (tidak redirect ke
     * situs Duitku) — hanya tersedia kalau admin sudah mengisi kode
     * metode QRIS di pengaturan gateway.
     */
    public function payQris(Invoice $invoice, PaymentGateway $gateway): View|RedirectResponse
    {
        return $this->payQrisView($invoice, $gateway, 'client.invoices.qris', 'client.invoices.show');
    }

    public function payQrisBootstrap(Invoice $invoice, PaymentGateway $gateway): View|RedirectResponse
    {
        return $this->payQrisView($invoice, $gateway, 'client.invoices.qris', 'client.invoices.show');
    }

    private function payQrisView(Invoice $invoice, PaymentGateway $gateway, string $view, string $backRoute): View|RedirectResponse
    {
        $this->authorizeOwner($invoice);

        try {
            app(InvoiceService::class)->assertPayable($invoice);
        } catch (BillingException $e) {
            return redirect()->route($backRoute, $invoice)->with('error', $e->getMessage());
        }

        if (! $gateway->supportsEmbeddedQris()) {
            return redirect()->route($backRoute, $invoice)
                ->with('error', 'QRIS tertanam belum diatur untuk gateway ini.');
        }

        $qris = $this->createQrisPaymentOnce($invoice, $gateway);

        if (! $qris['success']) {
            return redirect()->route($backRoute, $invoice)
                ->with('error', $qris['message']);
        }

        $payment = Payment::findOrFail($qris['payment_id']);
        $qrString = $qris['qr_string'];

        return view($view, [
            'invoice' => $invoice,
            'payment' => $payment,
            'qrString' => $qrString,
        ]);
    }

    /**
     * QRIS juga membuat transaksi eksternal, walaupun dipanggil dari halaman
     * GET. Seluruh pencarian/pembuatan payment dan HTTP call provider berada
     * di bawah lock invoice+gateway yang sama dengan alur pembayaran biasa.
     */
    private function createQrisPaymentOnce(Invoice $invoice, PaymentGateway $gateway): array
    {
        $result = Cache::lock(
            "payment-gateway-init:{$invoice->id}:{$gateway->id}",
            120
        )->get(function () use ($invoice, $gateway) {
            $state = DB::transaction(function () use ($invoice, $gateway) {
                $payment = Payment::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('payment_gateway_id', $gateway->id)
                    ->whereIn('status', ['initiated', 'pending'])
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    $amount = (float) $invoice->total;
                    $fee = $gateway->calculateFee($amount);

                    $payment = Payment::create([
                        'invoice_id'         => $invoice->id,
                        'client_id'          => $invoice->client_id,
                        'payment_gateway_id' => $gateway->id,
                        'amount'             => $amount,
                        'fee'                => $fee,
                        'total'              => $amount + $fee,
                        'currency'           => $gateway->currency,
                        'status'             => 'initiated',
                    ]);
                }

                $qrString = $payment->gateway_response['qrString']
                    ?? $payment->gateway_response['qrCode']
                    ?? null;

                if ($qrString && $payment->status === 'initiated') {
                    return [
                        'ready' => true,
                        'payment_id' => $payment->id,
                        'qr_string' => $qrString,
                    ];
                }

                // Payment pending berarti sedang menunggu verifikasi manual;
                // jangan mengubahnya menjadi transaksi QRIS baru.
                if ($payment->status !== 'initiated' || $payment->external_id) {
                    return [
                        'blocked' => true,
                        'message' => 'Sudah ada pembayaran berjalan untuk invoice ini. Selesaikan atau batalkan pembayaran tersebut terlebih dahulu.',
                    ];
                }

                return [
                    'payment_id' => $payment->id,
                    'needs_provider' => true,
                ];
            });

            if ($state['ready'] ?? false) {
                return [
                    'success' => true,
                    'payment_id' => $state['payment_id'],
                    'qr_string' => $state['qr_string'],
                ];
            }

            if ($state['blocked'] ?? false) {
                return [
                    'success' => false,
                    'message' => $state['message'],
                ];
            }

            $payment = Payment::findOrFail($state['payment_id']);
            $providerResult = PaymentGatewayFactory::make($gateway)->createQrisTransaction($payment);

            if (! $providerResult['success']) {
                $payment->update([
                    'status' => 'failed',
                    'gateway_response' => ['error' => $providerResult['message']],
                ]);

                return [
                    'success' => false,
                    'message' => 'Gagal membuat kode QRIS: ' . $providerResult['message'],
                ];
            }

            $payment->update([
                'external_id'      => $providerResult['external_id'],
                'expires_at'       => $providerResult['expires_at'],
                'gateway_response' => $providerResult['raw'],
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'qr_string' => $providerResult['qr_string'],
            ];
        });

        return is_array($result)
            ? $result
            : [
                'success' => false,
                'message' => 'Pembayaran sedang diproses. Silakan muat ulang beberapa saat lagi.',
            ];
    }

    /**
     * Dipoll dari halaman QRIS setiap beberapa detik untuk mendeteksi
     * pembayaran berhasil tanpa klien perlu memuat ulang manual. Webhook
     * dari Duitku yang benar-benar mengubah status — endpoint ini hanya
     * membaca status yang sudah tersimpan.
     */
    public function qrisStatus(Payment $payment)
    {
        $this->authorizeOwner($payment);

        return response()->json([
            'status' => $payment->status,
            'expired' => $payment->expires_at && $payment->expires_at->isPast() && $payment->status !== 'paid',
        ]);
    }

    /**
     * Klien mengunggah bukti transfer untuk pembayaran manual yang masih
     * menunggu.
     *
     * Sebelumnya kolom `proof_path` sudah ada di database sejak awal
     * tapi tidak pernah dipakai di mana pun — halaman invoice hanya
     * menyuruh klien "konfirmasi transfer" tanpa menjelaskan caranya,
     * dan admin harus menunggu klien menghubungi lewat chat/tiket secara
     * terpisah untuk tahu ada pembayaran yang perlu diperiksa.
     */
    public function confirmPayment(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizeOwner($payment);

        if ($payment->status !== 'pending') {
            return back()->with('error', 'Pembayaran ini sudah tidak bisa dikonfirmasi ulang.');
        }

        $data = $request->validate([
            'proof' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            'note'  => ['nullable', 'string', 'max:500'],
        ], [
            'proof.required' => 'Unggah bukti transfer terlebih dahulu.',
            'proof.max' => 'Ukuran berkas maksimal 5 MB.',
            'proof.mimes' => 'Berkas harus berupa gambar (JPG/PNG/WEBP) atau PDF.',
        ]);

        // Bukti transfer adalah data sensitif. Simpan di disk private supaya
        // symlink public/storage tidak dapat membukanya tanpa otorisasi.
        $path = $request->file('proof')->store('payment-proofs', 'local');

        $payment->update([
            'proof_path' => $path,
            'admin_note' => trim(($payment->admin_note ? $payment->admin_note . "\n\n" : '')
                . '[Klien] ' . ($data['note'] ?: 'Bukti transfer diunggah, menunggu verifikasi.')),
        ]);

        ActivityLog::record(
            'payment',
            'Bukti transfer diunggah: ' . $payment->reference,
            ($payment->client->name ?? '—') . ' — Rp ' . number_format((float) $payment->total, 0, ',', '.'),
            route('admin.payments.details', $payment),
            'warning',
            $payment->client_id,
        );

        // Admin perlu tahu ada yang perlu diverifikasi — tanpa notifikasi
        // ini, pembayaran bisa menunggu berhari-hari tanpa diperiksa kalau
        // admin tidak kebetulan membuka halaman Pembayaran.
        try {
            app(NotificationService::class)->paymentProofUploaded($payment);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Notifikasi bukti transfer gagal: ' . $e->getMessage());
        }

        return back()->with('success', 'Bukti transfer berhasil dikirim. Tim kami akan memverifikasi dalam 1x24 jam.');
    }

    /**
     * Sajikan bukti transfer milik klien sendiri — lihat penjelasan lengkap
     * di App\Http\Controllers\Admin\PaymentController::proof(). Dibuat
     * terpisah (bukan berbagi satu route) karena otorisasinya beda: di sini
     * dicek kepemilikan klien, bukan status login admin.
     */
    public function proofFile(Payment $payment): \Symfony\Component\HttpFoundation\StreamedResponse|Response
    {
        $this->authorizeOwner($payment);

        if (! $payment->proof_path || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($payment->proof_path)) {
            abort(404, 'Bukti transfer tidak ditemukan.');
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->response($payment->proof_path);
    }

    /**
     * Unduh invoice sebagai PDF.
     */
    public function downloadPdf(Invoice $invoice): Response
    {
        $this->authorizeOwner($invoice);

        $invoice->load(['order', 'items.order', 'client']);

        $pdf = Pdf::loadView('client.invoices.pdf', compact('invoice'))->setPaper('a4');

        return $pdf->download("Invoice-{$invoice->invoice_number}.pdf");
    }
}
