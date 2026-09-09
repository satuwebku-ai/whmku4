<?php

namespace App\Services\Notification;

use App\Models\PushSubscription;

class PushSubscriptionService
{
    /**
     * @param  array{endpoint: string, keys: array{p256dh: string, auth: string}}  $subscription  Objek PushSubscription browser (hasil .toJSON() di JS)
     */
    public function subscribe(object $subscribable, array $subscription, ?string $userAgent = null): PushSubscription
    {
        return PushSubscription::remember($subscribable, $subscription, $this->labelFromUserAgent($userAgent));
    }

    public function unsubscribe(object $subscribable, string $endpoint): void
    {
        $subscribable->pushSubscriptions()
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->delete();
    }

    /**
     * Ringkasan singkat biar user sendiri gampang kenali perangkat mana
     * yang mana di daftar langganannya ("Chrome di Windows", "Safari di
     * iPhone") -- bukan untuk fingerprinting, cuma label tampilan.
     */
    private function labelFromUserAgent(?string $userAgent): ?string
    {
        if (blank($userAgent)) {
            return null;
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Mac OS') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $os ? "{$browser} di {$os}" : $browser;
    }
}
