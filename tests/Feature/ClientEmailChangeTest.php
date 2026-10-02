<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Notifications\ClientEmailChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private function client(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'name' => 'Klien Uji',
            'email' => 'lama@example.test',
            'phone' => '081234567890',
            'password' => 'Rahasia123',
            'status' => 'active',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Klien Uji',
            'email' => 'lama@example.test',
            'phone' => '081234567890',
        ], $overrides);
    }

    public function test_email_cannot_change_without_password(): void
    {
        Notification::fake();
        $client = $this->client();

        $this->actingAs($client, 'client')
            ->post(route('client.profile.update'), $this->payload(['email' => 'baru@example.test']))
            ->assertSessionHasErrors('current_password');

        $this->assertSame('lama@example.test', $client->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_email_cannot_change_with_wrong_password(): void
    {
        $client = $this->client();

        $this->actingAs($client, 'client')
            ->post(route('client.profile.update'), $this->payload(['email' => 'baru@example.test', 'current_password' => 'salah']))
            ->assertSessionHasErrors('current_password');

        $this->assertSame('lama@example.test', $client->fresh()->email);
    }

    public function test_correct_password_changes_email_unverifies_it_and_warns_old_address(): void
    {
        Notification::fake();
        $client = $this->client();

        $this->actingAs($client, 'client')
            ->post(route('client.profile.update'), $this->payload(['email' => 'baru@example.test', 'current_password' => 'Rahasia123']))
            ->assertSessionHasNoErrors();

        $fresh = $client->fresh();
        $this->assertSame('baru@example.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);

        Notification::assertSentOnDemand(ClientEmailChanged::class, function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'lama@example.test'
                && ! str_contains($notification->maskedNewEmail, 'baru@');
        });
    }

    public function test_other_fields_do_not_need_a_password(): void
    {
        Notification::fake();
        $client = $this->client();

        $this->actingAs($client, 'client')
            ->post(route('client.profile.update'), $this->payload(['name' => 'Nama Baru']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama Baru', $client->fresh()->name);
        $this->assertNotNull($client->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_google_account_email_is_locked(): void
    {
        $client = $this->client(['google_id' => 'google-123']);

        $this->actingAs($client, 'client')
            ->post(route('client.profile.update'), $this->payload(['email' => 'baru@example.test', 'name' => 'Nama Google']));

        $fresh = $client->fresh();
        $this->assertSame('lama@example.test', $fresh->email);
        $this->assertSame('Nama Google', $fresh->name);
    }

    public function test_repeated_wrong_passwords_are_throttled(): void
    {
        $client = $this->client();
        $this->actingAs($client, 'client');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('client.profile.update'), $this->payload(['email' => 'baru@example.test', 'current_password' => 'salah']));
        }

        // Percobaan ke-6 ditolak walau passwordnya benar.
        $this->post(route('client.profile.update'), $this->payload(['email' => 'baru@example.test', 'current_password' => 'Rahasia123']))
            ->assertSessionHasErrors('current_password');

        $this->assertSame('lama@example.test', $client->fresh()->email);
    }
}
