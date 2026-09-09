<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Integrasi Xendit lewat Invoice API.
 *
 * Dokumentasi: https://developers.xendit.co/api-reference/#create-invoice
 *
 * Auth: HTTP Basic Auth, username = Secret API Key, password kosong.
 * Endpoint: https://api.xendit.co/v2/invoices
 *
 * Catatan: Xendit tidak memisahkan URL sandbox/production — yang membedakan
 * adalah API key yang dipakai (test key vs live key). Field "Mode" di admin
 * hanya penanda internal supaya tidak tertukar.
 */
class XenditService implements PaymentGatewayInterface
{
    protected const BASE_URL = 'https://api.xendit.co';

    public function __construct(protected PaymentGateway $gateway) {}

    public function createTransaction(Payment $payment): array
    {
        $client = $payment->client;

        $payload = [
            'external_id'      => $payment->reference,
            'amount'           => (float) $payment->total,
            'currency'         => $payment->currency ?: 'IDR',
            'description'      => 'Invoice ' . ($payment->invoice->invoice_number ?? $payment->reference),
            'payer_email'      => $client->email ?? null,
            'customer'         => [
                'given_names'   => $client->name ?? 'Customer',
                'email'         => $client->email ?? null,
                'mobile_number' => $client->phone ?? null,
            ],
            'success_redirect_url' => route('payment.finish', ['lumora_ref' => $payment->reference]),
            'failure_redirect_url' => route('payment.finish', ['lumora_ref' => $payment->reference]),
        ];

        try {
            $response = $this->client()->post('/v2/invoices', $payload);
            $body = $response->json();

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->extractError($body) ?? "Xendit mengembalikan HTTP {$response->status()}.",
                    'payment_url' => null,
                    'external_id' => null,
                    'raw' => $body,
                ];
            }

            return [
                'success'     => true,
                'message'     => 'Invoice Xendit berhasil dibuat.',
                'payment_url' => $body['invoice_url'] ?? null,
                'external_id' => $body['id'] ?? null,
                'raw'         => $body,
            ];
        } catch (Throwable $e) {
            Log::warning('Xendit createTransaction gagal: ' . $e->getMessage(), ['payment_id' => $payment->id]);

            return [
                'success' => false,
                'message' => 'Tidak bisa terhubung ke Xendit: ' . $e->getMessage(),
                'payment_url' => null,
                'external_id' => null,
                'raw' => null,
            ];
        }
    }

    /**
     * Xendit memverifikasi webhook lewat header x-callback-token yang harus
     * sama persis dengan Verification Token di dashboard Xendit.
     */
    public function handleCallback(Request $request): array
    {
        $token = $request->header('x-callback-token');
        $expected = (string) $this->gateway->callback_token;

        if (blank($expected)) {
            return ['success' => false, 'message' => 'Callback token belum diatur di pengaturan gateway.', 'status' => null, 'payment' => null];
        }

        if (! $token || ! hash_equals($expected, $token)) {
            Log::warning('Xendit callback ditolak: token tidak cocok.');

            return ['success' => false, 'message' => 'Callback token tidak valid.', 'status' => null, 'payment' => null];
        }

        $data = $request->all();
        $reference = $data['external_id'] ?? null;

        if (! $reference) {
            return ['success' => false, 'message' => 'external_id tidak ada di payload.', 'status' => null, 'payment' => null];
        }

        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            return ['success' => false, 'message' => "Pembayaran {$reference} tidak ditemukan.", 'status' => null, 'payment' => null];
        }

        $status = $this->mapStatus($data['status'] ?? '');

        if ($status === 'paid') {
            $payment->markAsPaid($data['payment_method'] ?? null, $data);
        } else {
            $payment->update([
                'status' => $status,
                'external_id' => $data['id'] ?? $payment->external_id,
                'gateway_response' => $data,
            ]);
        }

        return ['success' => true, 'message' => 'Webhook diproses.', 'status' => $status, 'payment' => $payment];
    }

    public function checkStatus(Payment $payment): array
    {
        if (! $payment->external_id) {
            return ['success' => false, 'message' => 'Pembayaran ini belum punya ID transaksi Xendit.', 'status' => null, 'raw' => null];
        }

        try {
            $response = $this->client()->get("/v2/invoices/{$payment->external_id}");
            $body = $response->json();

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->extractError($body) ?? 'Gagal mengambil status dari Xendit.',
                    'status' => null,
                    'raw' => $body,
                ];
            }

            return ['success' => true, 'message' => 'OK', 'status' => $this->mapStatus($body['status'] ?? ''), 'raw' => $body];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Tidak bisa terhubung ke Xendit: ' . $e->getMessage(), 'status' => null, 'raw' => null];
        }
    }

    protected function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID', 'SETTLED' => 'paid',
            'PENDING' => 'initiated',
            'EXPIRED' => 'expired',
            default   => 'failed',
        };
    }

    protected function extractError(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $message = $body['message'] ?? null;
        $code = $body['error_code'] ?? null;

        if ($message && $code) {
            return "{$message} ({$code})";
        }

        return $message;
    }

    protected function client(): PendingRequest
    {
        // Xendit: Secret Key sebagai username, password dikosongkan.
        return Http::baseUrl(self::BASE_URL)
            ->withBasicAuth((string) $this->gateway->server_key, '')
            ->acceptJson()
            ->asJson()
            ->timeout(25);
    }
}
