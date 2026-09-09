<?php

namespace App\Notifications\Channels;

use App\Models\Setting;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Channel notifikasi SMS.
 *
 * Dibuat pluggable sama seperti WhatsAppChannel — provider SMS di
 * Indonesia beda-beda format API-nya (Zenziva pakai userkey/passkey,
 * Twilio pakai Account SID/Auth Token internasional), jadi ditangani
 * terpisah, dengan opsi "custom" untuk gateway lain.
 *
 * SMS berbayar per pesan (beda dari email/WhatsApp), jadi notifikasi
 * yang ingin memakai channel ini HARUS eksplisit menyediakan method
 * `toSms($notifiable): string` -- tidak otomatis ikut semua notifikasi
 * seperti WhatsApp, supaya biaya kirim tetap terkendali.
 */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $number = $this->resolveNumber($notifiable);

        if (! $number) {
            return;
        }

        $message = $notification->toSms($notifiable);

        if (blank($message)) {
            return;
        }

        $this->dispatch($number, $message);
    }

    /**
     * Kirim SMS ke satu nomor. Dibuat publik supaya bisa dipakai juga
     * untuk tombol "Tes Kirim SMS" di Pengaturan.
     */
    public function dispatch(string $number, string $message): bool
    {
        $provider = Setting::get('sms_provider', 'none');

        if ($provider === 'none') {
            return false;
        }

        $number = $this->normalizeNumber($number);

        try {
            $response = match ($provider) {
                // Zenziva (reguler): userkey + passkey sebagai parameter
                'zenziva' => Http::timeout(20)->asForm()->post('https://reguler.zenziva.net/apps/smsapi.php', [
                    'userkey' => Setting::get('sms_userkey'),
                    'passkey' => Setting::get('sms_passkey'),
                    'nomor'   => $number,
                    'pesan'   => $message,
                ]),

                // Twilio: otentikasi Basic Auth (Account SID + Auth Token)
                'twilio' => Http::timeout(20)
                    ->withBasicAuth(Setting::get('sms_userkey'), Setting::get('sms_passkey'))
                    ->asForm()
                    ->post('https://api.twilio.com/2010-04-01/Accounts/' . Setting::get('sms_userkey') . '/Messages.json', [
                        'To'   => '+' . $number,
                        'From' => Setting::get('sms_sender'),
                        'Body' => $message,
                    ]),

                // Gateway lain dengan format JSON sederhana
                'custom' => Http::timeout(20)
                    ->withHeaders(['Authorization' => 'Bearer ' . Setting::get('sms_passkey')])
                    ->asJson()
                    ->post(Setting::get('sms_endpoint'), [
                        'phone'   => $number,
                        'message' => $message,
                    ]),

                default => null,
            };

            if (! $response || ! $response->successful()) {
                Log::warning('SMS gagal dikirim.', [
                    'provider' => $provider,
                    'status' => $response?->status(),
                    'body' => $response?->body(),
                ]);

                return false;
            }

            // Zenziva mengembalikan HTTP 200 bahkan untuk permintaan yang
            // gagal (status error ada di body JSON, bukan status code) —
            // tanpa cek ini, "berhasil" akan salah dilaporkan.
            if ($provider === 'zenziva') {
                $status = $response->json('status');

                if ($status !== null && (string) $status !== '1') {
                    Log::warning('SMS Zenziva ditolak.', ['body' => $response->body()]);

                    return false;
                }
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('SMS error: ' . $e->getMessage(), ['provider' => $provider]);

            return false;
        }
    }

    /**
     * Ambil nomor tujuan dari penerima notifikasi -- SMS memakai kolom
     * `phone` biasa, bukan nomor WhatsApp terpisah (berlaku untuk semua
     * nomor seluler, bukan cuma yang aktif WhatsApp).
     */
    private function resolveNumber(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            return $notifiable->routeNotificationForSms();
        }

        return $notifiable->phone ?? null;
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

        return '62' . $digits;
    }
}
