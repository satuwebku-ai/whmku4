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
 * Integrasi Midtrans lewat Snap API.
 *
 * Dokumentasi: https://docs.midtrans.com/reference/snap-1
 *
 * Auth: HTTP Basic Auth, username = Server Key, password kosong.
 * Endpoint Snap:
 *   sandbox    → https://app.sandbox.midtrans.com/snap/v1/transactions
 *   production → https://app.midtrans.com/snap/v1/transactions
 */
class MidtransService implements PaymentGatewayInterface
{
    public function __construct(protected PaymentGateway $gateway) {}

    public function createTransaction(Payment $payment): array
    {
        $client = $payment->client;

        $payload = [
            'transaction_details' => [
                'order_id'     => $payment->reference,
                // Midtrans hanya menerima bilangan bulat untuk IDR.
                'gross_amount' => (int) round((float) $payment->total),
            ],
            'customer_details' => [
                'first_name' => $client->name ?? 'Customer',
                'email'      => $client->email ?? '',
                'phone'      => $client->phone ?? '',
            ],
            'item_details' => [[
                'id'       => $payment->invoice->invoice_number ?? $payment->reference,
                'price'    => (int) round((float) $payment->total),
                'quantity' => 1,
                'name'     => 'Invoice ' . ($payment->invoice->invoice_number ?? $payment->reference),
            ]],
        ];

        try {
            $response = $this->client()->post($this->snapUrl(), $payload);
            $body = $response->json();

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->extractError($body) ?? "Midtrans mengembalikan HTTP {$response->status()}.",
                    'payment_url' => null,
                    'external_id' => null,
                    'raw' => $body,
                ];
            }

            return [
                'success'     => true,
                'message'     => 'Transaksi Midtrans berhasil dibuat.',
                'payment_url' => $body['redirect_url'] ?? null,
                'external_id' => $body['token'] ?? null,
                'raw'         => $body,
            ];
        } catch (Throwable $e) {
            Log::warning('Midtrans createTransaction gagal: ' . $e->getMessage(), ['payment_id' => $payment->id]);

            return [
                'success' => false,
                'message' => 'Tidak bisa terhubung ke Midtrans: ' . $e->getMessage(),
                'payment_url' => null,
                'external_id' => null,
                'raw' => null,
            ];
        }
    }

    /**
     * Notifikasi Midtrans diverifikasi lewat signature_key:
     * SHA512(order_id + status_code + gross_amount + server_key)
     */
    public function handleCallback(Request $request): array
    {
        $data = $request->all();

        $orderId      = $data['order_id'] ?? null;
        $statusCode   = $data['status_code'] ?? null;
        $grossAmount  = $data['gross_amount'] ?? null;
        $signature    = $data['signature_key'] ?? null;

        if (! $orderId || ! $signature) {
            return ['success' => false, 'message' => 'Payload notifikasi tidak lengkap.', 'status' => null, 'payment' => null];
        }

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->gateway->server_key);

        if (! hash_equals($expected, $signature)) {
            Log::warning('Midtrans callback ditolak: signature tidak cocok.', ['order_id' => $orderId]);

            return ['success' => false, 'message' => 'Signature tidak valid.', 'status' => null, 'payment' => null];
        }

        $payment = Payment::where('reference', $orderId)->first();

        if (! $payment) {
            return ['success' => false, 'message' => "Pembayaran {$orderId} tidak ditemukan.", 'status' => null, 'payment' => null];
        }

        $status = $this->mapStatus($data);

        if ($status === 'paid') {
            $payment->markAsPaid($data['payment_type'] ?? null, $data);
        } else {
            $payment->update([
                'status' => $status,
                'payment_method' => $data['payment_type'] ?? $payment->payment_method,
                'external_id' => $data['transaction_id'] ?? $payment->external_id,
                'gateway_response' => $data,
            ]);
        }

        return ['success' => true, 'message' => 'Notifikasi diproses.', 'status' => $status, 'payment' => $payment];
    }

    public function checkStatus(Payment $payment): array
    {
        try {
            $response = $this->client()->get($this->apiUrl() . "/v2/{$payment->reference}/status");
            $body = $response->json();

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $this->extractError($body) ?? 'Gagal mengambil status dari Midtrans.',
                    'status' => null,
                    'raw' => $body,
                ];
            }

            return ['success' => true, 'message' => 'OK', 'status' => $this->mapStatus($body), 'raw' => $body];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Tidak bisa terhubung ke Midtrans: ' . $e->getMessage(), 'status' => null, 'raw' => null];
        }
    }

    /**
     * Petakan transaction_status Midtrans ke status internal.
     */
    protected function mapStatus(array $data): string
    {
        $status = $data['transaction_status'] ?? '';
        $fraud = $data['fraud_status'] ?? 'accept';

        return match ($status) {
            'capture'  => $fraud === 'accept' ? 'paid' : 'pending',
            'settlement' => 'paid',
            'pending'  => 'initiated',
            'deny', 'cancel' => 'failed',
            'expire'   => 'expired',
            'refund', 'partial_refund' => 'refunded',
            default    => 'failed',
        };
    }

    protected function snapUrl(): string
    {
        return $this->gateway->isSandbox()
            ? 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            : 'https://app.midtrans.com/snap/v1/transactions';
    }

    protected function apiUrl(): string
    {
        return $this->gateway->isSandbox()
            ? 'https://api.sandbox.midtrans.com'
            : 'https://api.midtrans.com';
    }

    protected function extractError(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        $messages = $body['error_messages'] ?? null;

        if (is_array($messages)) {
            return implode(' ', $messages);
        }

        return $body['status_message'] ?? null;
    }

    protected function client(): PendingRequest
    {
        // Midtrans: Server Key sebagai username, password dikosongkan.
        return Http::withBasicAuth((string) $this->gateway->server_key, '')
            ->acceptJson()
            ->asJson()
            ->timeout(25);
    }
}
