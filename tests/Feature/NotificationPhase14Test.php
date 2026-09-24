<?php

namespace Tests\Feature;

use App\Jobs\Notification\DeliverNotification;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationPhase14Test extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_notification_is_queued_and_deduplicated(): void
    {
        Queue::fake();

        $client = Client::factory()->create();
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
        ]);

        app(NotificationService::class)->invoiceCreated($invoice->refresh());

        $this->assertSame(1, NotificationDelivery::query()
            ->where('event_key', 'invoice:created:' . $invoice->id)
            ->count());

        Queue::assertPushed(DeliverNotification::class);
    }

    public function test_sent_delivery_is_not_queued_again_on_replay(): void
    {
        Queue::fake();

        $client = Client::factory()->create();
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
        ]);

        $delivery = NotificationDelivery::where('event_key', 'invoice:created:' . $invoice->id)->firstOrFail();
        $delivery->update(['status' => 'sent']);

        Queue::fake()->except([]);
        app(NotificationService::class)->invoiceCreated($invoice->refresh());

        $this->assertSame(1, NotificationDelivery::query()
            ->where('event_key', 'invoice:created:' . $invoice->id)
            ->count());
    }
}
