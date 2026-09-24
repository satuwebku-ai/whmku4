<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        // reference dan total dibuat otomatis lewat Payment::booted() kalau
        // tidak diisi eksplisit, tapi factory tetap boleh menimpanya.
        $amount = fake()->randomFloat(2, 50000, 500000);

        return [
            'invoice_id' => Invoice::factory(),
            'client_id' => Client::factory(),
            'amount' => $amount,
            'fee' => 0,
            'total' => $amount,
            'currency' => 'IDR',
            'status' => 'initiated',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
