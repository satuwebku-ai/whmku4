<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\HostingAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HostingAccount>
 */
class HostingAccountFactory extends Factory
{
    protected $model = HostingAccount::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            // server_id/server sengaja null: layanan "manual" tanpa server
            // terhubung, jadi test tidak butuh provider API asli.
            'domain' => fake()->unique()->domainName(),
            'package' => 'Cloud Hosting - Pro',
            'panel' => 'cpanel',
            'price' => 100000,
            'billing_cycle' => 'monthly',
            'billing_mode' => 'invoice',
            'status' => 'pending',
            'next_due_date' => now()->addMonth(),
            'provision_status' => 'manual',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }
}
