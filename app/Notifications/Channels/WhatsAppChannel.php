<?php

namespace App\Notifications\Channels;

use App\Models\Setting;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Channel notifikasi WhatsApp.
 *
 * Dibuat pluggable karena tidak ada API WhatsApp resmi yang murah untuk
 * skala kecil di Indonesia — kebanyakan orang memakai gateway pihak ketiga
 * (Fonnte, Wablas, dsb). Ketiganya beda format, jadi ditangani terpisah,
 * dengan opsi "custom" untuk gateway lain yang formatnya mirip.
 *
 * Notifikasi yang ingin memakai channel ini cukup menyediakan method
 * `toWhatsApp($notifiable): string` yang mengembalikan isi pesannya.
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $number = $this->resolveNumber($notifiable);

        if (! $number) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if (blank($message)) {
            return;
        }

        $this->dispatch($number, $message);
    }

    /**
     * Kirim pesan ke satu nomor. Dibuat publik supaya bisa dipakai juga
     * untuk broadcast promo tanpa lewat sistem notifikasi.
     */
    public function dispatch(string $number, string $message): bool
    {
        $provider = Setting::get('wa_provider', 'none');
        $token = Setting::get('wa_token');

        if ($provider === 'none' || blank($token)) {
            return false;
        }

        $number = $this->normalizeNumber($number);

        try {
            $response = match ($provider) {
                // Fonnte: token di header Authorization
                'fonnte' => Http::timeout(20)
                    ->withHeaders(['Authorization' => $token])
                    ->asForm()
                    ->post('https://api.fonnte.com/send', [
                        'target' => $number,
                        'message' => $message,
                    ]),

                // Wablas: token di header, domain bisa berbeda per akun
                'wablas' => Http::timeout(20)
                    ->withHeaders(['Authorization' => $token])
                    ->asForm()
                    ->post(rtrim(Setting::get('wa_endpoint', 'https://console.wablas.com'), '/') . '/api/send-message', [
                        'phone' => $number,
                        'message' => $message,
                    ]),

                // Gateway lain dengan format JSON sederhana
                'custom' => Http::timeout(20)
                    ->withHeaders(['Authorization' => 'Bearer ' . $token])
                    ->asJson()
                    ->post(Setting::get('wa_endpoint'), [
                        'phone' => $number,
                        'message' => $message,
                    ]),

                default => null,
            };

            if (! $response || ! $response->successful()) {
                Log::warning('WhatsApp gagal dikirim.', [
                    'provider' => $provider,
                    'status' => $response?->status(),
                    'body' => $response?->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('WhatsApp error: ' . $e->getMessage(), ['provider' => $provider]);

            return false;
        }
    }

    /**
     * Ambil nomor tujuan dari penerima notifikasi.
     */
    private function resolveNumber(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForWhatsApp')) {
            return $notifiable->routeNotificationForWhatsApp();
        }

        return $notifiable->whatsapp_number ?? $notifiable->phone ?? null;
    }

    /**
     * Ubah nomor lokal jadi format internasional tanpa tanda plus.
     * "0812-3456-7890" → "6281234567890"
     */
    private function normalizeNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);

        if (str_starts_with($digits, '0')) {
            return '62' . ltrim($digits, '0');
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        // Nomor tanpa kode negara diasumsikan Indonesia.
        return '62' . $digits;
    }
}
