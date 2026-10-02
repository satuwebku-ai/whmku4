<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppWebhookAuthTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        Setting::put('wa_provider', 'fonnte', 'notification');
        Setting::put('wa_token', 'token-test', 'notification');
        Setting::put('wa_webhook_secret', 'rahasia-test-123', 'notification');
    }

    private function payload(string $number = '081234567890', string $msg = 'halo'): array
    {
        return ['sender' => $number, 'message' => $msg];
    }

    public function test_webhook_without_key_is_rejected_and_stores_nothing(): void
    {
        $this->configure();

        $this->post(route('webhook.whatsapp'), $this->payload())->assertStatus(403);
        $this->post(route('webhook.whatsapp') . '?key=salah', $this->payload())->assertStatus(403);

        $this->assertDatabaseCount('chat_conversations', 0);
    }

    public function test_webhook_is_rejected_when_no_secret_is_configured_yet(): void
    {
        Setting::put('wa_provider', 'fonnte', 'notification');

        $this->post(route('webhook.whatsapp') . '?key=apa-saja', $this->payload())->assertStatus(403);
        $this->assertDatabaseCount('chat_conversations', 0);
    }

    public function test_valid_key_stores_message_without_calling_ai_or_gateway_by_default(): void
    {
        $this->configure();
        Setting::put('ai_chat_enabled', '1', 'general');
        Setting::put('ai_chat_api_key', 'sk-test', 'general');
        Http::fake();

        $this->post(route('webhook.whatsapp') . '?key=rahasia-test-123', $this->payload('0812-3456-7890'))
            ->assertOk();

        $conversation = ChatConversation::first();
        $this->assertNotNull($conversation);
        $this->assertSame('whatsapp', $conversation->channel);
        $this->assertSame('081234567890', $conversation->phone);
        $this->assertSame(1, $conversation->messages()->count());

        // AI di WhatsApp mati secara default: tidak ada panggilan keluar sama sekali.
        Http::assertNothingSent();
    }

    public function test_invalid_numbers_are_ignored(): void
    {
        $this->configure();

        $this->post(route('webhook.whatsapp') . '?key=rahasia-test-123', $this->payload('12', 'halo'))->assertOk();
        $this->post(route('webhook.whatsapp') . '?key=rahasia-test-123', $this->payload('abc', 'halo'))->assertOk();

        $this->assertDatabaseCount('chat_conversations', 0);
    }

    public function test_sender_flood_is_capped(): void
    {
        $this->configure();

        for ($i = 0; $i < 25; $i++) {
            $this->post(route('webhook.whatsapp') . '?key=rahasia-test-123', $this->payload('081234567890', "pesan {$i}"))->assertOk();
        }

        $this->assertSame(20, ChatConversation::first()->messages()->count());
    }
}
