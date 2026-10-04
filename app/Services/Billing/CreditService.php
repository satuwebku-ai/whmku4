<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\BillingException;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;

/**
 * Titik masuk tunggal (dari sisi Services/Billing) untuk mutasi saldo
 * klien. Ledger sesungguhnya tetap Client::adjustBalance() — kelas ini
 * TIDAK mengubah kolom balance secara langsung, supaya jejak di
 * client_balance_logs tetap satu-satunya sumber kebenaran seperti yang
 * sudah didesain di model Client.
 */
class CreditService
{
    public function balance(Client $client): float
    {
        return (float) $client->balance;
    }

    /**
     * Tambah saldo klien (topup, refund, penyesuaian admin positif, dst).
     */
    public function credit(
        Client $client,
        float $amount,
        string $description,
        string $type = 'topup',
        ?Invoice $invoice = null,
        ?Admin $admin = null,
        ?string $idempotencyKey = null,
    ): Credit {
        if ($amount <= 0) {
            throw new BillingException('Nominal kredit saldo harus lebih dari 0.');
        }

        return $client->adjustBalance($amount, $type, $description, $invoice, $admin, $idempotencyKey);
    }

    /**
     * Kurangi saldo klien (pembayaran invoice pakai saldo, penyesuaian
     * admin negatif, dst). Melempar BillingException kalau saldo tidak
     * cukup — dicek DI SINI (bukan cuma dipercayakan ke pemanggil) supaya
     * tidak ada jalur yang lupa mengecek dan membuat saldo klien minus.
     */
    public function debit(
        Client $client,
        float $amount,
        string $description,
        string $type = 'payment',
        ?Invoice $invoice = null,
        ?Admin $admin = null,
        ?string $idempotencyKey = null,
    ): Credit {
        if ($amount <= 0) {
            throw new BillingException('Nominal debit saldo harus lebih dari 0.');
        }

        // Pengecekan saldo cukup (di bawah kunci klien) ada di
        // Client::adjustBalance(), jadi tidak ada jalur yang bisa
        // melewatinya. Melempar BillingException kalau tidak cukup.
        return $client->adjustBalance(-1 * $amount, $type, $description, $invoice, $admin, $idempotencyKey);
    }

}
