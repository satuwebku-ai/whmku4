<?php

namespace App\Exceptions\Affiliate;

use Exception;

/**
 * Exception dasar untuk seluruh domain Affiliate (registrasi, komisi,
 * wallet, payout). Blueprint tidak mendaftarkan exception khusus untuk
 * Affiliate (bab 7 hanya mencakup Domain/Requirement/Order/Billing/
 * Payment/Hosting/Client/Security/Integration) -- kelas ini dibuat
 * mengikuti pola yang sama supaya konsisten, bukan memaksakan pinjam
 * App\Exceptions\Billing\BillingException yang secara semantik keliru
 * dipakai di luar konteks invoice/pembayaran.
 */
class AffiliateException extends Exception
{
    //
}
