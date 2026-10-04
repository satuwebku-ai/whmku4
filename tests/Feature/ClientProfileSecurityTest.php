<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Notifications\ClientSecurityAlert;
use App\Notifications\SecurityOtpCode;
use App\Notifications\VerifyEmailCode;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function client(array $attrs = []): Client
    {
        return Client::create(array_merge([
            'name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '081234567890',
            'password' => 'Lama12345', 'status' => 'active', 'email_verified_at' => now(),
        ], $attrs));
    }

    private function payload(Client $c, array $over = []): array
    {
        return array_merge([
            'name' => $c->name, 'email' => $c->email, 'phone' => '081234567890', 'country' => 'ID',
        ], $over);
    }

    public function test_email_change_waits_for_code_and_keeps_old_email(): void
    {
        Notification::fake();
        $c = $this->client();

        $this->actingAs($c, 'client')
            ->post(route('client.profile.update'), $this->payload($c, ['email' => 'Baru@Example.com', 'current_password' => 'Lama12345']))
            ->assertSessionHasNoErrors();

        $c->refresh();
        $this->assertSame('budi@example.com', $c->email);
        $this->assertSame('baru@example.com', $c->pending_email);
        Notification::assertSentTo($c, ClientSecurityAlert::class);
        Notification::assertSentTimes(VerifyEmailCode::class, 1);
    }

    public function test_email_verification_swaps_email(): void
    {
        Notification::fake();
        $c = $this->client();
        $code = $c->startEmailChange('baru@example.com');

        $this->actingAs($c, 'client')
            ->post(route('client.profile.email.verify'), ['code' => '000000' === $code ? '111111' : '000000'])
            ->assertSessionHasErrors('code');
        $this->assertSame('budi@example.com', $c->fresh()->email);

        $this->actingAs($c->fresh(), 'client')
            ->post(route('client.profile.email.verify'), ['code' => $code])
            ->assertSessionHas('success');

        $this->assertSame('baru@example.com', $c->fresh()->email);
        $this->assertNull($c->fresh()->pending_email);
    }

    public function test_email_change_requires_current_password(): void
    {
        $c = $this->client();

        $this->actingAs($c, 'client')
            ->post(route('client.profile.update'), $this->payload($c, ['email' => 'x@example.com', 'current_password' => 'salah']))
            ->assertSessionHasErrors('current_password');

        $this->assertNull($c->fresh()->pending_email);
    }

    public function test_whatsapp_is_normalized_and_country_validated(): void
    {
        $c = $this->client();

        $this->actingAs($c, 'client')
            ->post(route('client.profile.update'), $this->payload($c, ['whatsapp_number' => '0812-3456-7890', 'notify_whatsapp' => 1]))
            ->assertSessionHasNoErrors();
        $this->assertSame('+6281234567890', $c->fresh()->whatsapp_number);

        $this->actingAs($c->fresh(), 'client')
            ->post(route('client.profile.update'), $this->payload($c, ['country' => 'Atlantis']))
            ->assertSessionHasErrors('country');

        $this->actingAs($c->fresh(), 'client')
            ->post(route('client.profile.update'), $this->payload($c, ['phone' => 'abc']))
            ->assertSessionHasErrors('phone');
    }

    public function test_password_change_with_current_password_and_alert(): void
    {
        Notification::fake();
        $c = $this->client();

        $this->actingAs($c, 'client')
            ->post(route('client.profile.password'), ['current_password' => 'Lama12345', 'password' => 'Baru12345', 'password_confirmation' => 'Baru12345'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Baru12345', $c->fresh()->password));
        Notification::assertSentTo($c, ClientSecurityAlert::class);

        $this->actingAs($c->fresh(), 'client')
            ->post(route('client.profile.password'), ['current_password' => 'Baru12345', 'password' => 'Baru12345', 'password_confirmation' => 'Baru12345'])
            ->assertSessionHasErrors('password');
    }

    public function test_google_account_sets_password_via_otp_email(): void
    {
        Notification::fake();
        $c = $this->client(['google_id' => 'g-1', 'password_set_by_user' => false]);

        // Tanpa OTP ditolak.
        $this->actingAs($c, 'client')
            ->post(route('client.profile.password'), ['password' => 'Baru12345', 'password_confirmation' => 'Baru12345'])
            ->assertSessionHasErrors('otp');

        $sent = null;
        $this->actingAs($c, 'client')
            ->post(route('client.profile.otp'), ['purpose' => 'password_change', 'channel' => 'email'])
            ->assertSessionHas('success');
        Notification::assertSentTo($c, SecurityOtpCode::class, function ($n) use (&$sent) { $sent = $n->code; return $n->channel === 'email'; });

        $this->post(route('client.profile.password'), ['otp' => $sent === '000000' ? '111111' : '000000', 'password' => 'Baru12345', 'password_confirmation' => 'Baru12345'])
            ->assertSessionHasErrors('otp');

        $this->post(route('client.profile.password'), ['otp' => $sent, 'password' => 'Baru12345', 'password_confirmation' => 'Baru12345'])
            ->assertSessionHasNoErrors();

        $c->refresh();
        $this->assertTrue(Hash::check('Baru12345', $c->password));
        $this->assertTrue($c->password_set_by_user);
    }

    public function test_whatsapp_channel_unavailable_without_gateway(): void
    {
        $c = $this->client(['whatsapp_number' => '+6281234567890']);

        $this->actingAs($c, 'client')
            ->post(route('client.profile.otp'), ['purpose' => 'password_change', 'channel' => 'whatsapp'])
            ->assertSessionHas('error');
    }

    public function test_opt_in_otp_mode_requires_code_not_password(): void
    {
        Notification::fake();
        $c = $this->client();

        $this->actingAs($c, 'client')->post(route('client.profile.password-otp'))->assertSessionHas('success');
        $this->assertTrue($c->fresh()->password_otp_enabled);

        $this->actingAs($c->fresh(), 'client')
            ->post(route('client.profile.password'), ['current_password' => 'Lama12345', 'password' => 'Baru12345', 'password_confirmation' => 'Baru12345'])
            ->assertSessionHasErrors('otp');

        // Mematikan butuh password.
        $this->actingAs($c->fresh(), 'client')
            ->post(route('client.profile.password-otp'), ['current_password' => 'salah'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($c->fresh(), 'client')
            ->post(route('client.profile.password-otp'), ['current_password' => 'Lama12345'])
            ->assertSessionHas('success');
        $this->assertFalse($c->fresh()->password_otp_enabled);
    }

    public function test_session_dropped_after_password_changed_elsewhere(): void
    {
        $c = $this->client();

        $this->actingAs($c, 'client')->get(route('client.profile'))->assertOk();

        $c->forceFill(['password' => 'Diganti12345'])->save();

        $this->get(route('client.profile'))->assertRedirect(route('client.login'));
    }

    public function test_phone_normalizer_handles_foreign_numbers(): void
    {
        $this->assertSame('+60123456789', PhoneNumber::normalize('+60 12-345 6789'));
        $this->assertSame('+6281234567890', PhoneNumber::normalize('081234567890'));
        $this->assertNull(PhoneNumber::normalize('123'));
    }
}
