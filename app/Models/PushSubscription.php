<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PushSubscription extends Model
{
    protected $fillable = [
        'subscribable_type', 'subscribable_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth_token', 'label',
    ];

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Simpan/perbarui satu langganan push -- di-upsert berdasarkan hash
     * endpoint, supaya subscribe ulang dari browser yang sama (mis.
     * setelah kunci enkripsinya di-refresh browser) tidak menumpuk baris
     * duplikat.
     */
    public static function remember(object $subscribable, array $subscription, ?string $label = null): self
    {
        $endpoint = $subscription['endpoint'];

        return static::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'subscribable_type' => $subscribable->getMorphClass(),
                'subscribable_id'   => $subscribable->getKey(),
                'endpoint'          => $endpoint,
                'p256dh'            => $subscription['keys']['p256dh'] ?? '',
                'auth_token'        => $subscription['keys']['auth'] ?? '',
                'label'             => $label,
            ]
        );
    }
}
