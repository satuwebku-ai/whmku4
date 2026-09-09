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
 * Integrasi Duitku lewat Invoice API v2 — mendukung VA, e-wallet (OVO,
 * DANA, ShopeePay), QRIS, dan kartu kredit lewat satu halaman pembayaran,
 * mirip pola Midtrans Snap / Xendit Invoice.
 *
 * Dokumentasi resmi: https://docs.duitku.com
 * (kalau alamat API di bawah berubah suatu saat, cek dokumentasi itu —
 * Duitku jarang mengubahnya, tapi bukan tidak mungkin.)
 *
 * Field yang dipakai (mengikuti kolom generik yang sudah ada, supaya
 * tidak perlu migrasi tambahan):
 *   - server_key → API Key      (Dashboard Duitku → Pengaturan → API Key)
 *   - client_key → Merchant Code
 *
 * Autentikasi tidak memakai Bearer token, melainkan tanda tangan MD5 yang
 * disertakan di tiap body permintaan — kombinasi berbeda tergantung jenis
 * permintaannya (lihat masing-masing method).
 */
class DuitkuService implements PaymentGatewayInterface
{
    protected const SANDBOX_URL = 'https://sandbox.duitku.com';
    protected const PRODUCTION_URL = 'https://passport.duitku.com';

    public function __construct(protected PaymentGateway $gateway) {}

    public function createTransaction(Payment $payment): array
    {
        // Duitku MEWAJIBKAN paymentMethod diisi eksplisit di setiap
        // transaksi (dikonfirmasi dari dokumentasi resmi & pesan error
        // "paymentMethod is mandatory" yang benar-benar terjadi) — tidak
        // ada mode "biarkan Duitku tampilkan halaman pilihan sendiri"
        // seperti Midtrans Snap. Klien harus memilih dulu lewat
        // DuitkuController::selectMethod(), yang menyimpan pilihannya ke
        // kolom payment_method SEBELUM method ini dipanggil.
        if (blank($payment->payment_method)) {
            return [
                'success' => false,
                'message' => 'Metode pembayaran Duitku belum dipilih. Silakan pilih metode dulu di halaman sebelumnya.',
                'payment_url' => null,
                'external_id' => null,
                'raw' => null,
            ];
        }

        $client = $payment->client;
        $merchantCode = (string) $this->gateway->client_key;
        $apiKey = (string) $this->gateway->server_key;

        // Duitku mewajibkan nominal berupa bilangan bulat (tanpa desimal)
        // untuk perhitungan tanda tangan maupun payload-nya.
        $amount = (int) round((float) $payment->total);
        $orderId = $payment->reference;

        $signature = md5($merchantCode . $orderId . $amount . $apiKey);

        $payload = [
            'merchantCode'    => $merchantCode,
            'paymentAmount'   => $amount,
            'merchantOrderId' => $orderId,
            'paymentMethod'   => $payment->payment_method,
            'productDetails'  => 'Invoice ' . ($payment->invoice->invoice_number ?? $orderId),
            'email'           => $client->email ?? 'pelanggan@example.com',
            'customerVaName'  => $client->name ?? 'Pelanggan',
            'callbackUrl'     => route('payment.webhook', ['driver' => 'duitku']),
            'returnUrl'       => route('payment.finish', ['lumora_ref' => $orderId]),
            'signature'       => $signature,
        ];

        // phoneNumber cuma disertakan kalau memang terisi — sebagian API
        // (termasuk kemungkinan Duitku) menolak permintaan sebagai tidak
        // valid kalau field-nya dikirim sebagai `null` eksplisit di JSON,
        // beda dari sekadar tidak menyertakan field itu sama sekali.
        if (filled($client->phone)) {
            $payload['phoneNumber'] = $client->phone;
        }

        try {
            $response = $this->client()->post('/webapi/api/merchant/v2/inquiry', $payload);
            $body = $response->json();

            if (! $response->successful() || ($body['statusCode'] ?? null) === '01') {
                // Dicatat lengkap ke log — pesan yang ditampilkan ke
                // klien tetap ringkas, tapi admin bisa lihat respons
                // mentah Duitku sesungguhnya di storage/logs/laravel.log
                // untuk tahu ALASAN sebenarnya di balik HTTP 400/dst.
                Log::warning('Duitku createTransaction ditolak', [
                    'payment_id' => $payment->id,
                    'http_status' => $response->status(),
                    'response_body' => $body,
                    'payload_terkirim' => array_diff_key($payload, ['signature' => '']),
                ]);

                return [
                    'success' => false,
                    'message' => $body['statusMessage'] ?? $body['Message'] ?? $body['message'] ?? "Duitku mengembalikan HTTP {$response->status()}.",
                    'payment_url' => null,
                    'external_id' => null,
                    'raw' => $body,
                ];
            }

            return [
                'success'     => true,
                'message'     => 'Transaksi Duitku berhasil dibuat.',
                'payment_url' => $body['paymentUrl'] ?? null,
                'external_id' => $body['reference'] ?? null,
                'raw'         => $body,
            ];
        } catch (Throwable $e) {
            Log::warning('Duitku createTransaction gagal: ' . $e->getMessage(), ['payment_id' => $payment->id]);

            return [
                'success' => false,
                'message' => 'Tidak bisa terhubung ke Duitku: ' . $e->getMessage(),
                'payment_url' => null,
                'external_id' => null,
                'raw' => null,
            ];
        }
    }

    /**
     * Buat transaksi QRIS yang kodenya ditampilkan LANGSUNG di halaman
     * invoice (tidak redirect ke situs Duitku), dengan memaksa
     * `paymentMethod` ke kode yang diisi admin di pengaturan gateway.
     *
     * Beda dari createTransaction(): di sana paymentMethod sengaja
     * dikosongkan supaya Duitku menampilkan halaman pilihan sendiri.
     * Di sini kita memaksa satu metode spesifik supaya responsnya berisi
     * `qrString` — teks mentah kode QR yang bisa digambar sebagai QR code
     * di halaman kita sendiri.
     *
     * @return array{success: bool, message: string, qr_string: ?string, external_id: ?string, expires_at: ?\Carbon\Carbon, raw: mixed}
     */
    public function createQrisTransaction(Payment $payment): array
    {
        $client = $payment->client;
        $merchantCode = (string) $this->gateway->client_key;
        $apiKey = (string) $this->gateway->server_key;
        $methodCode = (string) $this->gateway->qris_method_code;

        $amount = (int) round((float) $payment->total);
        $orderId = $payment->reference;
        $expiryMinutes = 30;

        $signature = md5($merchantCode . $orderId . $amount . $apiKey);

        $payload = [
            'merchantCode'    => $merchantCode,
            'paymentAmount'   => $amount,
            'merchantOrderId' => $orderId,
            'paymentMethod'   => $methodCode,
            'productDetails'  => 'Invoice ' . ($payment->invoice->invoice_number ?? $orderId),
            'email'           => $client->email ?? 'pelanggan@example.com',
            'customerVaName'  => $client->name ?? 'Pelanggan',
            'callbackUrl'     => route('payment.webhook', ['driver' => 'duitku']),
            'returnUrl'       => route('client.invoices.show', $payment->invoice_id),
            'expiryPeriod'    => $expiryMinutes,
            'signature'       => $signature,
        ];

        if (filled($client->phone)) {
            $payload['phoneNumber'] = $client->phone;
        }

        try {
            $response = $this->client()->post('/webapi/api/merchant/v2/inquiry', $payload);
            $body = $response->json();

            $qrString = $body['qrString'] ?? $body['qrCode'] ?? null;

            if (! $response->successful() || ! $qrString) {
                Log::warning('Duitku createQrisTransaction ditolak', [
                    'payment_id' => $payment->id,
                    'http_status' => $response->status(),
                    'response_body' => $body,
                    'payload_terkirim' => array_diff_key($payload, ['signature' => '']),
                ]);

                return [
                    'success' => false,
                    'message' => $body['statusMessage'] ?? $body['Message'] ?? $body['message'] ?? 'Duitku tidak mengembalikan kode QRIS. Periksa apakah kode metode "' . $methodCode . '" sudah benar dan aktif di akun Duitku Anda.',
                    'qr_string' => null,
                    'external_id' => null,
                    'expires_at' => null,
                    'raw' => $body,
                ];
            }

            return [
                'success' => true,
                'message' => 'OK',
                'qr_string' => $qrString,
                'external_id' => $body['reference'] ?? null,
                'expires_at' => now()->addMinutes($expiryMinutes),
                'raw' => $body,
            ];
        } catch (Throwable $e) {
            Log::warning('Duitku createQrisTransaction gagal: ' . $e->getMessage(), ['payment_id' => $payment->id]);

            return [
                'success' => false,
                'message' => 'Tidak bisa terhubung ke Duitku: ' . $e->getMessage(),
                'qr_string' => null,
                'external_id' => null,
                'expires_at' => null,
                'raw' => null,
            ];
        }
    }

    /**
     * GET PAYMENT METHOD — daftar metode yang aktif untuk akun ini beserta
     * biayanya, dari endpoint resmi terpisah (bukan endpoint transaksi).
     * WAJIB dipanggil dulu sebelum createTransaction(), karena akun ini
     * (dan sepertinya akun Duitku pada umumnya sejak API v2) mewajibkan
     * `paymentMethod` diisi eksplisit — tidak ada mode "biarkan Duitku
     * tampilkan halaman pilihan sendiri" seperti Midtrans Snap.
     *
     * Endpoint & rumus tanda tangan dikonfirmasi dari dokumentasi resmi
     * docs.duitku.com — BEDA dari signature endpoint lain (pakai SHA256,
     * bukan MD5, dan menyertakan datetime).
     */
    public function getPaymentMethods(float $amount): array
    {
        $merchantCode = (string) $this->gateway->client_key;
        $apiKey = (string) $this->gateway->server_key;
        $datetime = now()->format('Y-m-d H:i:s');
        $amountInt = (int) round($amount);

        $signature = hash('sha256', $merchantCode . $amountInt . $datetime . $apiKey);

        try {
            $response = $this->client()->post('/webapi/api/merchant/paymentmethod/getpaymentmethod', [
                'merchantcode' => $merchantCode,
                'amount' => $amountInt,
                'datetime' => $datetime,
                'signature' => $signature,
            ]);

            $body = $response->json();

            if (! $response->successful()) {
                Log::warning('Duitku getPaymentMethods gagal', ['http_status' => $response->status(), 'response_body' => $body]);

                return ['success' => false, 'message' => $body['Message'] ?? $body['message'] ?? "HTTP {$response->status()}", 'methods' => []];
            }

            return [
                'success' => true,
                'message' => 'OK',
                'methods' => $body['paymentFee'] ?? [],
            ];
        } catch (Throwable $e) {
            Log::warning('Duitku getPaymentMethods error: ' . $e->getMessage());

            return ['success' => false, 'message' => $e->getMessage(), 'methods' => []];
        }
    }

    /**
     * Label kategori untuk pengelompokan tampilan — dari daftar kode resmi
     * di dokumentasi Duitku (34 kode, per Februari 2026).
     */
    public static function methodCategory(string $code): string
    {
        return match (true) {
            $code === 'VC' => 'Kartu Kredit',
            in_array($code, ['BC', 'M2', 'VA', 'I1', 'B1', 'BT', 'A1', 'AG', 'NC', 'BR', 'S1', 'DM', 'BV'], true) => 'Virtual Account',
            in_array($code, ['FT', 'IR'], true) => 'Ritel (Alfamart/Indomaret/Pos)',
            in_array($code, ['OV', 'SA', 'LF', 'LA', 'DA', 'SL', 'OL'], true) => 'E-Wallet',
            in_array($code, ['SP', 'NQ', 'GQ', 'SQ'], true) => 'QRIS',
            in_array($code, ['DN', 'AT'], true) => 'Paylater',
            $code === 'JP' => 'E-Banking',
            in_array($code, ['T1', 'T2', 'T3'], true) => 'E-Commerce',
            default => 'Lainnya',
        };
    }

    /**
     * Duitku memverifikasi callback lewat tanda tangan MD5 yang disertakan
     * di body permintaan itu sendiri (bukan header terpisah seperti
     * Xendit) — dihitung ulang di sini dan dicocokkan.
     */
    public function handleCallback(Request $request): array
    {
        $data = $request->all();

        $merchantCode = (string) $this->gateway->client_key;
        $apiKey = (string) $this->gateway->server_key;

        $amount = (string) ($data['amount'] ?? '');
        $orderId = (string) ($data['merchantOrderId'] ?? '');
        $signature = (string) ($data['signature'] ?? '');

        $expected = md5($merchantCode . $amount . $orderId . $apiKey);

        if (blank($signature) || ! hash_equals($expected, $signature)) {
            Log::warning('Duitku callback ditolak: signature tidak cocok.', ['order_id' => $orderId]);

            return ['success' => false, 'message' => 'Signature tidak valid.', 'status' => null, 'payment' => null];
        }

        if (! $orderId) {
            return ['success' => false, 'message' => 'merchantOrderId tidak ada di payload.', 'status' => null, 'payment' => null];
        }

        $payment = Payment::where('reference', $orderId)->first();

        if (! $payment) {
            return ['success' => false, 'message' => "Pembayaran {$orderId} tidak ditemukan.", 'status' => null, 'payment' => null];
        }

        $status = $this->mapResultCode((string) ($data['resultCode'] ?? ''));

        if ($status === 'paid') {
            $payment->markAsPaid($data['paymentCode'] ?? 'Duitku', $data);
        } else {
            $payment->update([
                'status' => $status,
                'external_id' => $data['reference'] ?? $payment->external_id,
                'gateway_response' => $data,
            ]);
        }

        return ['success' => true, 'message' => 'Webhook diproses.', 'status' => $status, 'payment' => $payment];
    }

    public function checkStatus(Payment $payment): array
    {
        $merchantCode = (string) $this->gateway->client_key;
        $apiKey = (string) $this->gateway->server_key;
        $orderId = $payment->reference;

        $signature = md5($merchantCode . $orderId . $apiKey);

        try {
            $response = $this->client()->post('/webapi/api/merchant/transactionStatus', [
                'merchantCode' => $merchantCode,
                'merchantOrderId' => $orderId,
                'signature' => $signature,
            ]);

            $body = $response->json();

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => $body['statusMessage'] ?? 'Gagal mengambil status dari Duitku.',
                    'status' => null,
                    'raw' => $body,
                ];
            }

            return [
                'success' => true,
                'message' => 'OK',
                'status' => $this->mapResultCode((string) ($body['statusCode'] ?? '')),
                'raw' => $body,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Tidak bisa terhubung ke Duitku: ' . $e->getMessage(), 'status' => null, 'raw' => null];
        }
    }

    /**
     * "00" konsisten dipakai Duitku sebagai kode sukses, baik di respons
     * transactionStatus (statusCode) maupun payload callback (resultCode).
     */
    protected function mapResultCode(string $code): string
    {
        return match ($code) {
            '00' => 'paid',
            '01' => 'initiated', // menunggu pembayaran
            '02' => 'expired',  // batal / kedaluwarsa
            default => 'failed',
        };
    }

    protected function client(): PendingRequest
    {
        $base = $this->gateway->isSandbox() ? self::SANDBOX_URL : self::PRODUCTION_URL;

        return Http::baseUrl($base)
            ->acceptJson()
            ->asJson()
            ->timeout(25);
    }
}
