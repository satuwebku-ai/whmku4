<?php

namespace Tests\Feature;

use App\Jobs\Notification\DeliverNotification;
use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Models\Setting;
use App\Notifications\InvoiceDueReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceReminderCatchUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_catches_up_only_the_latest_missed_upcoming_stage(): void
    {
        Queue::fake();
        Setting::put('reminder_days_before', '7,3,1', 'system');
        Setting::put('reminder_days_after', '1,7', 'system');

        $dueDate = today()->addDays(2)->toDateString();
        $invoice = Invoice::factory()->create([
            'status' => 'unpaid',
            'due_date' => $dueDate,
        ]);

        Artisan::call('lumora:send-reminders');

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_type' => InvoiceDueReminder::class,
            'event_key' => "invoice:reminder:{$invoice->id}:{$dueDate}:before:3",
        ]);
        $this->assertDatabaseMissing('notification_deliveries', [
            'notification_type' => InvoiceDueReminder::class,
            'event_key' => "invoice:reminder:{$invoice->id}:{$dueDate}:before:7",
        ]);

        Queue::assertPushed(DeliverNotification::class, fn (DeliverNotification $job) =>
            $job->notification instanceof InvoiceDueReminder && $job->notification->daysLeft === 2
        );
    }

    public function test_catches_up_only_the_latest_missed_overdue_stage(): void
    {
        Queue::fake();
        Setting::put('reminder_days_before', '7,3,1', 'system');
        Setting::put('reminder_days_after', '1,7', 'system');

        $dueDate = today()->subDays(10)->toDateString();
        $invoice = Invoice::factory()->create([
            'status' => 'overdue',
            'due_date' => $dueDate,
        ]);

        Artisan::call('lumora:send-reminders');

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_type' => InvoiceDueReminder::class,
            'event_key' => "invoice:reminder:{$invoice->id}:{$dueDate}:after:7",
        ]);
        $this->assertDatabaseMissing('notification_deliveries', [
            'notification_type' => InvoiceDueReminder::class,
            'event_key' => "invoice:reminder:{$invoice->id}:{$dueDate}:after:1",
        ]);
    }

    public function test_sent_stage_is_not_enqueued_again_on_replay(): void
    {
        Queue::fake();
        Setting::put('reminder_days_before', '7,3,1', 'system');

        $dueDate = today()->addDays(2)->toDateString();
        $invoice = Invoice::factory()->create([
            'status' => 'unpaid',
            'due_date' => $dueDate,
        ]);

        Artisan::call('lumora:send-reminders');

        $delivery = NotificationDelivery::query()
            ->where('event_key', "invoice:reminder:{$invoice->id}:{$dueDate}:before:3")
            ->firstOrFail();
        $queuedBeforeReplay = Queue::pushed(DeliverNotification::class)->count();

        Artisan::call('lumora:send-reminders');

        $this->assertSame($queuedBeforeReplay, Queue::pushed(DeliverNotification::class)->count());

        $delivery->update(['status' => 'sent']);
        Artisan::call('lumora:send-reminders');

        $this->assertSame($queuedBeforeReplay, Queue::pushed(DeliverNotification::class)->count());
        $this->assertSame(1, NotificationDelivery::query()
            ->where('event_key', "invoice:reminder:{$invoice->id}:{$dueDate}:before:3")
            ->count());
    }

    public function test_failed_stage_can_be_requeued_for_retry(): void
    {
        Queue::fake();
        Setting::put('reminder_days_before', '7,3,1', 'system');

        $dueDate = today()->addDays(2)->toDateString();
        $invoice = Invoice::factory()->create([
            'status' => 'unpaid',
            'due_date' => $dueDate,
        ]);

        Artisan::call('lumora:send-reminders');

        $delivery = NotificationDelivery::query()
            ->where('event_key', "invoice:reminder:{$invoice->id}:{$dueDate}:before:3")
            ->firstOrFail();
        $delivery->update(['status' => 'failed']);
        $queuedBeforeRetry = Queue::pushed(DeliverNotification::class)->count();

        Artisan::call('lumora:send-reminders');

        $this->assertGreaterThan($queuedBeforeRetry, Queue::pushed(DeliverNotification::class)->count());
        $this->assertSame('pending', $delivery->fresh()->status);
    }

    public function test_dry_run_does_not_create_a_reminder_delivery(): void
    {
        Queue::fake();
        Setting::put('reminder_days_before', '7,3,1', 'system');

        $dueDate = today()->addDays(2)->toDateString();
        $invoice = Invoice::factory()->create([
            'status' => 'unpaid',
            'due_date' => $dueDate,
        ]);
        $queuedBeforeDryRun = Queue::pushed(DeliverNotification::class)->count();

        Artisan::call('lumora:send-reminders', ['--dry' => true]);

        $this->assertDatabaseMissing('notification_deliveries', [
            'notification_type' => InvoiceDueReminder::class,
            'event_key' => "invoice:reminder:{$invoice->id}:{$dueDate}:before:3",
        ]);
        $this->assertSame($queuedBeforeDryRun, Queue::pushed(DeliverNotification::class)->count());
    }
}