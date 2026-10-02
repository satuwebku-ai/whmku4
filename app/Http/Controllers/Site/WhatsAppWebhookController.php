<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\Setting;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Chat\AiChatService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Terima pesan WhatsApp MASUK dari gateway (Fonnte/Wablas), simpan ke
 * Live Chat admin, dan (kalau diaktifkan) balas lewat bot AI.
 *
 * KEAMANAN: Fonnte/Wablas tidak menandatangani webhook, jadi endpoint ini
 * dijaga rahasia di alamatnya: ?key=<wa_webhook_secret>. Tanpa kunci yang
 * benar, siapa pun bisa memalsukan pesan masuk dari nomor mana saja --
 * membuat percakapan palsu, menghabiskan token AI, dan membuat bot
 * mengirim WhatsApp ke nomor korban. Alamat lengkap (beserta kunci) ada di
 * Pengaturan -> Notifikasi dan di `php artisan lumora:whatsapp-status`.
 *
 * Percakapan memakai tabel chat_conversations/chat_messages yang sama
 * dengan widget web (dibedakan lewat kolom channel).
 */
class WhatsAppWebhookController extends Controller
{
    /** Pesan per nomor per 10 menit sebelum diabaikan (anti banjir). */
    private const MAX_PER_SENDER = 20;

    private const MAX_MESSAGE_LENGTH = 2000;

    public function handle(Request $request): Response
    {
        if (! $this->authentic($request)) {
            Log::warning('WhatsApp webhook: DITOLAK, kunci rahasia salah atau belum diatur.', [
                'ip' => $request->ip(),
            ]);

            return response('Forbidden', 403);
        }

        $provider = Setting::get('wa_provider', 'none');

        [$fromNumber, $messageText] = match ($provider) {
            'fonnte' => $this->parseFonnte($request),
            'wablas' => $this->parseWablas($request),
            default  => [null, null],
        };

        $fromNumber = $this->cleanNumber($fromNumber);
        $messageText = is_string($messageText) ? trim(Str::limit($messageText, self::MAX_MESSAGE_LENGTH, '')) : null;

        // Dicatat untuk diagnosis (dibaca `lumora:whatsapp-status`). Isi
        // pesan pelanggan hanya ikut dicatat saat APP_DEBUG menyala.
        Log::info('WhatsApp webhook diterima', array_filter([
            'provider' => $provider,
            'parsed_from' => $fromNumber ? substr($fromNumber, 0, 5) . '***' . substr($fromNumber, -2) : null,
            'message_length' => $messageText ? mb_strlen($messageText) : 0,
            'raw_body' => config('app.debug') ? $request->all() : null,
        ], fn ($v) => $v !== null));

        if ($provider === 'none') {
            Log::warning('WhatsApp webhook: diterima tapi wa_provider belum diatur (masih "none") di Pengaturan -> Notifikasi.');

            return response('OK', 200);
        }

        if (! $fromNumber || blank($messageText)) {
            // Ping/verifikasi gateway sering berisi body kosong atau field
            // lain -- dibalas 200 supaya gateway tidak mengulang.
            Log::warning('WhatsApp webhook: gagal mengurai nomor/pesan dari payload (nomor tidak valid atau pesan kosong).');

            return response('OK', 200);
        }

        $limiterKey = 'wa-webhook|' . $fromNumber;
        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_PER_SENDER)) {
            Log::warning('WhatsApp webhook: pengirim melewati batas pesan, diabaikan.');

            return response('OK', 200);
        }
        RateLimiter::hit($limiterKey, 600);

        $conversation = ChatConversation::firstOrCreate(
            ['phone' => $fromNumber, 'channel' => 'whatsapp'],
            ['guest_token' => (string) Str::uuid(), 'status' => 'open', 'name' => $fromNumber]
        );

        $conversation->messages()->create([
            'sender' => 'user',
            'message' => $messageText,
        ]);

        $conversation->increment('unread_for_admin');
        $conversation->update(['last_message_at' => now(), 'status' => 'open']);

        // Bot AI di WhatsApp MATI secara default. AI dikhususkan untuk
        // Live Chat web; nyalakan lewat Pengaturan -> Live Chat kalau mau.
        if (Setting::get('ai_chat_whatsapp', '0') !== '1') {
            return response('OK', 200);
        }

        $botMessage = (new AiChatService())->reply($conversation);

        if ($botMessage) {
            $sent = app(WhatsAppChannel::class)->dispatch($fromNumber, $botMessage->message);

            if (! $sent) {
                Log::warning('WhatsApp AI: balasan bot gagal dikirim ke gateway', ['conversation_id' => $conversation->id]);
            }
        }

        return response('OK', 200);
    }

    /**
     * Alamat webhook lengkap beserta kunci rahasia -- yang didaftarkan di
     * dashboard gateway. Kunci dibuat otomatis kalau belum ada.
     */
    public static function url(): string
    {
        return route('webhook.whatsapp') . '?key=' . urlencode(static::ensureSecret());
    }

    public static function ensureSecret(): string
    {
        $secret = (string) Setting::get('wa_webhook_secret');

        if ($secret === '') {
            $secret = Str::random(40);
            Setting::put('wa_webhook_secret', $secret, 'notification');
        }

        return $secret;
    }

    private function authentic(Request $request): bool
    {
        $secret = (string) Setting::get('wa_webhook_secret');

        // Belum ada kunci = ditolak. Lebih aman daripada membiarkan
        // endpoint terbuka sampai admin sempat mengaturnya.
        if ($secret === '') {
            return false;
        }

        $given = (string) ($request->query('key') ?? $request->header('X-Webhook-Secret') ?? '');

        return $given !== '' && hash_equals($secret, $given);
    }

    /**
     * Ambil digit saja; nomor valid 8-15 digit (standar E.164).
     * "0062..." dan "+62..." dirapikan ke "62...".
     */
    private function cleanNumber(mixed $number): ?string
    {
        if (! is_string($number) && ! is_numeric($number)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $number);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return preg_match('/^\d{8,15}$/', $digits) ? $digits : null;
    }

    /**
     * Format webhook Fonnte: field "sender" (nomor pengirim) dan
     * "message" (isi pesan) di body POST.
     */
    private function parseFonnte(Request $request): array
    {
        return [$request->input('sender'), $request->input('message')];
    }

    /**
     * Format webhook Wablas: field "phone" dan "message".
     */
    private function parseWablas(Request $request): array
    {
        return [$request->input('phone'), $request->input('message')];
    }
}
