<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('08##########'),
            'company' => fake()->optional()->company(),
            'address' => fake()->optional()->address(),
            'city' => fake()->city(),
            'country' => 'Indonesia',
            'password' => Hash::make('password'),
            'status' => 'active',
            'balance' => 0,
            'notify_promo' => true,
            'notify_whatsapp' => false,
            'notify_sms' => false,
            'two_factor_enabled' => false,
            'email_verified_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
