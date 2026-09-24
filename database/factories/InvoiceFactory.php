<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Invoice::booted() selalu menghitung ulang `total` dari
     * amount/tax/discount saat dibuat (recalculateTotal()), jadi
     * mengoper 'total' langsung ke create() normalnya tertimpa oleh
     * `amount` acak dari factory. Beberapa test yang sudah ada memang
     * mengoper 'total' langsung dan mengharapkan nilai itu dihormati —
     * jadi di sini, kalau 'total' dioper eksplisit, `amount` disesuaikan
     * supaya recalculateTotal() menghasilkan total yang sama persis.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Invoice $invoice) {
            if ($invoice->getAttribute('total') !== null) {
                $invoice->amount = (float) $invoice->total
                    - (float) ($invoice->tax ?? 0)
                    + (float) ($invoice->discount ?? 0);
            }
        });
    }

    public function definition(): array
    {
        // invoice_number dibuat otomatis lewat Invoice::booted(), dan total
        // selalu dihitung ulang dari amount/tax/discount di sana juga —
        // jadi factory ini cukup memberi input mentahnya.
        return [
            'client_id' => Client::factory(),
            'amount' => fake()->randomFloat(2, 50000, 500000),
            'tax' => 0,
            'discount' => 0,
            'status' => 'unpaid',
            'is_topup' => false,
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function topup(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_topup' => true,
        ]);
    }
}