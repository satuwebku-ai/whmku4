<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    /**
     * Daftar driver yang tersedia — dipakai juga untuk dropdown di form admin.
     */
    public const DRIVERS = [
        'midtrans' => 'Midtrans (Snap)',
        'xendit'   => 'Xendit (Invoice)',
        'duitku'   => 'Duitku (VA, E-Wallet, QRIS)',
        'manual'   => 'Transfer Manual',
    ];

    public static function make(PaymentGateway $gateway): PaymentGatewayInterface
    {
        return match ($gateway->driver) {
            'midtrans' => new MidtransService($gateway),
            'xendit'   => new XenditService($gateway),
            'duitku'   => new DuitkuService($gateway),
            'manual'   => new ManualTransferService($gateway),
            default    => throw new InvalidArgumentException("Payment driver [{$gateway->driver}] tidak dikenali."),
        };
    }
}
