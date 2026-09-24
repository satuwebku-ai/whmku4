<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Billing\BillingException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Billing\InvoiceService;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function payments(Request $request): View
    {
        return $this->renderList($request, null);
    }

    public function paymentsBootstrap(Request $request): View
    {
        return view('admin.payments.index', $this->listData($request, null));
    }

    public function initiated(Request $request): View
    {
        return $this->renderList($request, 'initiated');
    }

    public function initiatedBootstrap(Request $request): View
    {
        return view('admin.payments.index', $this->listData($request, 'initiated'));
    }

    public function pending(Request $request): View
    {
        return $this->renderList($request, 'pending');
    }

    public function pendingBootstrap(Request $request): View
    {
        return view('admin.payments.index', $this->listData($request, 'pending'));
    }

    public function paid(Request $request): View
    {
        return $this->renderList($request, 'paid');
    }

    public function paidBootstrap(Request $request): View
    {
        return view('admin.payments.index', $this->listData($request, 'paid'));
    }

    public function failed(Request $request): View
    {
        return $this->renderList($request, 'failed');
    }

    public function failedBootstrap(Request $request): View
    {
        return view('admin.payments.index', $this->listData($request, 'failed'));
    }

    public function refunded(Request $request): View
    {
        return $this->renderList($request, 'refunded');
    }

    public function refundedBootstrap(Request $request): View
    {
        return view('admin.payments.index', $this->listData($request, 'refunded'));
    }

    private function renderList(Request $request, ?string $status): View
    {
        return view('admin.payments.index', $this->listData($request, $status));
    }

    private function listData(Request $request, ?string $status): array
    {
        $payments = Payment::query()
            ->with(['client', 'invoice', 'gateway'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return ['payments' => $payments, 'activeStatus' => $status];
    }

    public function details(Payment $payment): View
    {
        $payment->load(['client', 'invoice', 'gateway']);

        return view('admin.payments.details', compact('payment'));
    }

    public function detailsBootstrap(Payment $payment): View
    {
        $payment->load(['client', 'invoice', 'gateway']);

        return view('admin.payments.details', compact('payment'));
    }

    /**
     * Sajikan bukti transfer lewat rute Laravel yang butuh login admin —
     * bukan lewat symlink public/storage.
     *
     * Dua alasan sekaligus:
     *  1. Beberapa hosting shared (termasuk yang dipakai di sini) memblokir
     *     Apache mengikuti symlink karena kebijakan keamanan, membuat
     *     `storage/...` selalu mengembalikan 403 meski `artisan storage:link`
     *     sudah berhasil. Menyajikan lewat PHP tidak bergantung symlink itu.
     *  2. URL publik `storage/payment-proofs/...` bisa diakses SIAPA SAJA
     *     tanpa login — bukti transfer sering memuat nama & sebagian nomor
     *     rekening. Lewat rute ini, hanya admin yang login yang bisa buka.
     */
    public function proof(Payment $payment): StreamedResponse|Response
    {
        if (! $payment->proof_path || ! Storage::disk('local')->exists($payment->proof_path)) {
            abort(404, 'Bukti transfer tidak ditemukan.');
        }

        return Storage::disk('local')->response($payment->proof_path);
    }

    /**
     * Buat pembayaran baru untuk sebuah invoice, lalu inisiasi ke gateway.
     */
    public function create(Request $request): View
    {
        $invoices = Invoice::with('client')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->latest()
            ->get();

        $gateways = PaymentGateway::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.payments.form', compact('invoices', 'gateways'));
    }

    public function createBootstrap(Request $request): View
    {
        $invoices = Invoice::with('client')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->latest()
            ->get();

        $gateways = PaymentGateway::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.payments.form', compact('invoices', 'gateways'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id'         => ['required', 'exists:invoices,id'],
            'payment_gateway_id' => ['required', 'exists:payment_gateways,id'],
        ]);

        $invoice = Invoice::findOrFail($data['invoice_id']);
        $gateway = PaymentGateway::findOrFail($data['payment_gateway_id']);

        if ($invoice->status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas, tidak perlu pembayaran baru.');
        }

        /*
         * Cegah dua admin membuat payment aktif untuk invoice yang sama.
         * Query biasa "cek lalu insert" tidak cukup karena dua request dapat
         * melewati cek sebelum salah satunya melakukan INSERT.
         */
        $state = Cache::lock("payment-create:invoice:{$invoice->id}", 120)->get(function () use ($invoice, $gateway) {
            return DB::transaction(function () use ($invoice, $gateway) {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

                if ($invoice->status === 'paid') {
                    return ['paid' => true];
                }

                $existing = Payment::where('invoice_id', $invoice->id)
                    ->whereIn('status', ['initiated', 'pending'])
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return [
                        'payment_id' => $existing->id,
                        'existing' => true,
                    ];
                }

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

                return [
                    'payment_id' => $payment->id,
                    'existing' => false,
                ];
            });
        });

        if (! is_array($state)) {
            return back()->with('error', 'Pembayaran sedang dibuat oleh request lain. Silakan coba lagi sebentar.');
        }

        if ($state['paid'] ?? false) {
            return back()->with('error', 'Invoice ini sudah lunas, tidak perlu pembayaran baru.');
        }

        $payment = Payment::findOrFail($state['payment_id']);

        if ($state['existing'] ?? false) {
            return redirect()->route('admin.payments.details', $payment)
                ->with('error', 'Sudah ada pembayaran berjalan untuk invoice ini (' . $payment->reference . '). Selesaikan atau batalkan dulu sebelum membuat yang baru.');
        }

        $result = $this->createGatewayTransactionOnce($payment, $gateway);

        if (! $result['success'] && ($result['busy'] ?? false)) {
            return redirect()->route('admin.payments.details', $payment)
                ->with('error', $result['message']);
        }

        if (! $result['success']) {
            $payment->update(['status' => 'failed', 'gateway_response' => ['error' => $result['message']]]);

            return redirect()->route('admin.payments.details', $payment)
                ->with('error', 'Gagal membuat transaksi di gateway: ' . $result['message']);
        }

        $payment->update([
            'payment_url'      => $result['payment_url'],
            'external_id'      => $result['external_id'],
            'gateway_response' => $result['raw'],
        ]);

        return redirect()->route('admin.payments.details', $payment)
            ->with('success', $result['message']);
    }

    /**
     * Serialisasi HTTP call ke gateway untuk invoice+gateway yang sama.
     * Lock database pada pembuatan row tidak boleh ditahan selama call
     * eksternal, sehingga lock cache dipakai untuk fase provider-nya.
     */
    private function createGatewayTransactionOnce(Payment $payment, PaymentGateway $gateway): array
    {
        $result = Cache::lock(
            "payment-gateway-init:{$payment->invoice_id}:{$gateway->id}",
            120
        )->get(function () use ($payment, $gateway) {
            $payment->refresh();

            if ($payment->external_id || $payment->payment_url) {
                return [
                    'success' => true,
                    'message' => 'Pembayaran sudah berhasil diinisialisasi.',
                    'payment_url' => $payment->payment_url,
                    'external_id' => $payment->external_id,
                    'raw' => $payment->gateway_response,
                ];
            }

            return PaymentGatewayFactory::make($gateway)->createTransaction($payment);
        });

        return is_array($result) ? $result : [
            'success' => false,
            'busy' => true,
            'message' => 'Pembayaran sedang diproses. Silakan coba lagi sebentar.',
            'payment_url' => null,
            'external_id' => null,
            'raw' => null,
        ];
    }

    /**
     * Setujui pembayaran manual — tandai lunas & lunasi invoice.
     */
    public function approve(Request $request): RedirectResponse
    {
        $payment = Payment::findOrFail($request->input('payment_id'));

        if ($payment->status !== 'pending') {
            return back()->with('error', 'Approval manual hanya boleh dilakukan untuk pembayaran berstatus pending.');
        }

        try {
            app(InvoiceService::class)->assertPayable($payment->invoice);
        } catch (BillingException $e) {
            return back()->with('error', $e->getMessage());
        }

        $payment->update(['admin_note' => $request->input('admin_note')]);
        $payment->markAsPaid($payment->payment_method ?? 'Transfer Manual');

        return back()->with('success', "Pembayaran {$payment->reference} disetujui. Invoice terkait ditandai lunas.");
    }

    /**
     * Tolak pembayaran.
     */
    public function reject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_id' => ['required', 'exists:payments,id'],
            'admin_note' => ['nullable', 'string'],
        ]);

        $payment = Payment::findOrFail($data['payment_id']);
        $payment->update(['status' => 'failed', 'admin_note' => $data['admin_note']]);

        return back()->with('success', "Pembayaran {$payment->reference} ditolak.");
    }

    /**
     * Rekonsiliasi manual — tanya status langsung ke gateway.
     */
    public function checkStatus(Payment $payment): RedirectResponse
    {
        if (! $payment->gateway) {
            return back()->with('error', 'Pembayaran ini tidak terhubung ke gateway manapun.');
        }

        $result = PaymentGatewayFactory::make($payment->gateway)->checkStatus($payment);

        if (! $result['success']) {
            return back()->with('error', 'Gagal cek status: ' . $result['message']);
        }

        if ($result['status'] === 'paid' && $payment->status !== 'paid') {
            $payment->markAsPaid($payment->payment_method, $result['raw'] ?? []);

            return back()->with('success', 'Status terverifikasi LUNAS di gateway. Invoice ikut ditandai lunas.');
        }

        if ($result['status'] && $result['status'] !== $payment->status) {
            $payment->update(['status' => $result['status'], 'gateway_response' => $result['raw']]);
        }

        return back()->with('success', 'Status di gateway: ' . ($result['status'] ?? 'tidak diketahui'));
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $payment->delete();

        return redirect()->route('admin.payments')->with('success', 'Data pembayaran berhasil dihapus.');
    }
}
