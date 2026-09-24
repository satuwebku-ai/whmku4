<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'dedupe_key',
        'notifiable_type',
        'notifiable_id',
        'notification_type',
        'event_key',
        'status',
        'attempts',
        'sent_at',
        'failed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
