<?php

namespace App\Services\Billing;

use App\Exceptions\Billing\BillingException;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

        if ($idempotencyKey) {
            $existing = Credit::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        // Lock the client before checking the balance. This makes concurrent
        // debits serialize instead of both observing the same old balance.
        $lockedClient = Client::query()->lockForUpdate()->findOrFail($client->id);
        if ((float) $lockedClient->balance < $amount) {
            throw new BillingException('Saldo tidak cukup untuk transaksi ini.');
        }

        return $lockedClient->adjustBalance(-1 * $amount, $type, $description, $invoice, $admin, $idempotencyKey);
    }

    public function history(Client $client, int $perPage = 15)
    {
        /** @var HasMany $logs */
        $logs = $client->balanceLogs();

        return $logs->latest()->paginate($perPage);
    }
}
