<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Notification\NotificationService;
use App\Services\Payment\PaymentGatewayFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
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
     * Klien memilih gateway dan memulai pembayaran.
     */
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

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwner($invoice);

        if ($invoice->status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas.');
        }

        if ($invoice->status === 'cancelled') {
            return back()->with('error', 'Invoice ini sudah dibatalkan dan tidak bisa dibayar.');
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

        $result = PaymentGatewayFactory::make($gateway)->createTransaction($payment);

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

        $data = $request->validate([
            'payment_gateway_id' => ['required', 'exists:payment_gateways,id'],
            'method_code' => ['required', 'string', 'max:2'],
        ]);

        $gateway = PaymentGateway::where('is_active', true)->where('driver', 'duitku')->findOrFail($data['payment_gateway_id']);

        $payment = $this->getOrCreatePendingPayment($invoice, $gateway);

        if ($payment instanceof RedirectResponse) {
            return $payment;
        }

        // Kode metode yang dipilih klien disimpan di sini SEBELUM
        // createTransaction() dipanggil — DuitkuService membacanya dari
        // sini karena interface createTransaction(Payment $payment) tidak
        // punya parameter tambahan untuk ini (dipakai bersama gateway lain).
        $payment->update(['payment_method' => $data['method_code']]);

        $result = PaymentGatewayFactory::make($gateway)->createTransaction($payment);

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
        $amount = (float) $invoice->total;
        $fee = $gateway->calculateFee($amount);

        $payment = Payment::where('invoice_id', $invoice->id)
            ->where('payment_gateway_id', $gateway->id)
            ->whereIn('status', ['initiated', 'pending'])
            ->latest('id')
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
    }

    private function finalizePaymentAttempt(Payment $payment, PaymentGateway $gateway, array $result): RedirectResponse
    {
        if (! $result['success']) {
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

        if (! $gateway->supportsEmbeddedQris()) {
            return redirect()->route($backRoute, $invoice)
                ->with('error', 'QRIS tertanam belum diatur untuk gateway ini.');
        }

        if ($invoice->status === 'paid') {
            return redirect()->route($backRoute, $invoice)
                ->with('success', 'Invoice ini sudah lunas.');
        }

        $payment = Payment::where('invoice_id', $invoice->id)
            ->where('payment_gateway_id', $gateway->id)
            ->where('status', 'initiated')
            ->where('expires_at', '>', now())
            ->whereNotNull('external_id')
            ->latest('id')
            ->first();

        $qrString = $payment?->gateway_response['qrString'] ?? $payment?->gateway_response['qrCode'] ?? null;

        if (! $payment || ! $qrString) {
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

            $result = PaymentGatewayFactory::make($gateway)->createQrisTransaction($payment);

            if (! $result['success']) {
                $payment->update(['status' => 'failed', 'gateway_response' => ['error' => $result['message']]]);

                return redirect()->route($backRoute, $invoice)
                    ->with('error', 'Gagal membuat kode QRIS: ' . $result['message']);
            }

            $payment->update([
                'external_id'       => $result['external_id'],
                'expires_at'        => $result['expires_at'],
                'gateway_response'  => $result['raw'],
            ]);

            $qrString = $result['qr_string'];
        }

        return view($view, [
            'invoice' => $invoice,
            'payment' => $payment,
            'qrString' => $qrString,
        ]);
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

        $path = $request->file('proof')->store('payment-proofs', 'public');

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

        if (! $payment->proof_path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($payment->proof_path)) {
            abort(404, 'Bukti transfer tidak ditemukan.');
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->response($payment->proof_path);
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
