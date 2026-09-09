<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;

/**
 * Transfer manual — tidak memanggil API manapun.
 *
 * Alur: klien transfer ke rekening yang tertera di "instruksi pembayaran",
 * lalu admin memverifikasi lewat tombol Approve/Reject di halaman
 * detail pembayaran.
 */
class ManualTransferService implements PaymentGatewayInterface
{
    public function __construct(protected PaymentGateway $gateway) {}

    public function createTransaction(Payment $payment): array
    {
        // Tidak ada API yang dipanggil; pembayaran langsung menunggu
        // verifikasi admin.
        $payment->update(['status' => 'pending']);

        return [
            'success'     => true,
            'message'     => 'Pembayaran manual dibuat. Menunggu verifikasi admin setelah klien transfer.',
            'payment_url' => null,
            'external_id' => null,
            'raw'         => ['instructions' => $this->gateway->instructions],
        ];
    }

    public function handleCallback(Request $request): array
    {
        return [
            'success' => false,
            'message' => 'Transfer manual tidak menerima callback otomatis. Verifikasi dilakukan admin.',
            'status' => null,
            'payment' => null,
        ];
    }

    public function checkStatus(Payment $payment): array
    {
        return [
            'success' => true,
            'message' => 'Status transfer manual ditentukan oleh verifikasi admin.',
            'status' => $payment->status,
            'raw' => null,
        ];
    }
}
