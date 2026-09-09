<?php

namespace App\Notifications\Channels;

use App\Models\Setting;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Channel notifikasi Push (browser) lewat protokol Web Push standar
 * (VAPID + enkripsi RFC 8291) -- tidak butuh gateway pihak ketiga sama
 * sekali, langsung ke Chrome/Firefox/Safari, makanya beda dari
 * WhatsApp/SMS yang tergantung provider eksternal.
 *
 * Push GRATIS per pesan (beda dari SMS), jadi cakupannya dibuat seluas
 * WhatsApp -- notifikasi mana saja yang menyediakan toPush() otomatis
 * ikut, tidak dibatasi ketat seperti SMS.
 */
class PushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toPush')) {
            return;
        }

        $payload = $notification->toPush($notifiable);

        if (blank($payload['title'] ?? null)) {
            return;
        }

        $this->dispatch($notifiable, $payload['title'], $payload['body'] ?? '', $payload['url'] ?? null);
    }

    /**
     * Kirim ke SEMUA perangkat/browser yang didaftarkan notifiable ini.
     * Dipakai juga oleh tombol "Tes Push" di Pengaturan.
     *
     * @return int Jumlah perangkat yang berhasil menerima.
     */
    public function dispatch(object $notifiable, string $title, string $body = '', ?string $url = null): int
    {
        $publicKey = Setting::get('vapid_public_key');
        $privateKey = Setting::get('vapid_private_key');

        if (blank($publicKey) || blank($privateKey)) {
            return 0;
        }

        if (! method_exists($notifiable, 'pushSubscriptions')) {
            return 0;
        }

        $subscriptions = $notifiable->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => 'mailto:' . (Setting::get('admin_email') ?: 'admin@' . parse_url(config('app.url'), PHP_URL_HOST)),
                    'publicKey'  => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('Push gagal: VAPID tidak valid — ' . $e->getMessage());

            return 0;
        }

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $url ?: config('app.url'),
            'icon'  => Setting::get('favicon_url') ?: null,
        ]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth_token],
                ]),
                $payload
            );
        }

        $success = 0;
        $expiredIds = [];

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            $sub = $subscriptions->firstWhere('endpoint', $endpoint);

            if ($report->isSuccess()) {
                $success++;
                continue;
            }

            // Endpoint kedaluwarsa (410 Gone / 404) = browser sudah
            // unsubscribe atau uninstall -- daripada terus dicoba tiap
            // kali notifikasi terkirim (mubazir & bikin log penuh
            // sampah), langganan basi ini langsung dibuang di sini.
            if ($report->isSubscriptionExpired() && $sub) {
                $expiredIds[] = $sub->id;
            } else {
                Log::warning('Push gagal dikirim.', ['endpoint' => $endpoint, 'reason' => $report->getReason()]);
            }
        }

        if (! empty($expiredIds)) {
            \App\Models\PushSubscription::whereIn('id', $expiredIds)->delete();
        }

        return $success;
    }
}
