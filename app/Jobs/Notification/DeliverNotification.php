<?php

namespace App\Jobs\Notification;

use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Notification;
use Throwable;

class DeliverNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $deliveryId,
        public object $notifiable,
        public Notification $notification,
    ) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $delivery = NotificationDelivery::find($this->deliveryId);

        if (! $delivery || $delivery->status === 'sent') {
            return;
        }

        $delivery->increment('attempts');
        $this->notifiable->notify($this->notification);

        $delivery->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'failed_at' => null,
            'error' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        NotificationDelivery::whereKey($this->deliveryId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error' => mb_substr($exception->getMessage(), 0, 4000),
        ]);
    }
}
