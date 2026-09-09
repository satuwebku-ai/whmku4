<?php

namespace App\Services\Payment\Contracts;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Buat transaksi di sisi gateway dan kembalikan URL pembayaran.
     *
     * @return array{success: bool, message: string, payment_url: ?string, external_id: ?string, raw: mixed}
     */
    public function createTransaction(Payment $payment): array;

    /**
     * Tangani notifikasi/webhook dari gateway.
     * Implementasi WAJIB memverifikasi keaslian request (signature/token)
     * sebelum mengubah status pembayaran apapun.
     *
     * @return array{success: bool, message: string, status: ?string, payment: ?Payment}
     */
    public function handleCallback(Request $request): array;

    /**
     * Cek status transaksi langsung ke gateway (untuk rekonsiliasi manual).
     *
     * @return array{success: bool, message: string, status: ?string, raw: mixed}
     */
    public function checkStatus(Payment $payment): array;
}
