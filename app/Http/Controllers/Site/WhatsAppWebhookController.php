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
use Illuminate\Support\Str;

/**
 * Terima pesan WhatsApp MASUK dari gateway (Fonnte/Wablas), lalu balas
 * otomatis lewat bot AI -- kalau aktif.
 *
 * SENGAJA memakai tabel chat_conversations/chat_messages yang SAMA
 * dengan widget chat web (dibedakan lewat kolom channel), supaya:
 *   - Admin bisa lihat & balas percakapan WhatsApp dari panel yang
 *     sudah ada (admin.chats), tidak perlu UI terpisah.
 *   - AiChatService (bot AI) dipakai APA ADANYA, tidak diubah sama
 *     sekali -- dia sudah generik soal channel-nya.
 *   - WhatsAppChannel::dispatch() (yang sudah ada, sudah teruji untuk
 *     kirim notifikasi) dipakai ulang untuk mengirim balasannya.
 *
 * Fonnte dan Wablas mengirim payload webhook dengan bentuk berbeda,
 * jadi keduanya ditangani terpisah lewat deteksi field yang ada.
 */
class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $provider = Setting::get('wa_provider', 'none');

        [$fromNumber, $messageText] = match ($provider) {
            'fonnte' => $this->parseFonnte($request),
            'wablas' => $this->parseWablas($request),
            default  => [null, null],
        };

        // SELALU dicatat -- ini yang paling penting untuk diagnosis.
        // Tanpa ini, kalau format field gateway ternyata beda dari yang
        // diasumsikan (mis. Fonnte kadang kirim "message" sebagai
        // object, bukan string), webhook diam total tanpa jejak sama
        // sekali, dan kita tidak akan pernah tahu penyebabnya.
        Log::info('WhatsApp webhook diterima', [
            'provider' => $provider,
            'parsed_from' => $fromNumber,
            'parsed_message' => $messageText,
            'raw_body' => $request->all(),
        ]);

        if ($provider === 'none') {
            Log::warning('WhatsApp webhook diterima tapi wa_provider belum diatur (masih "none") di Pengaturan → Live Chat.');

            return response('OK', 200);
        }

        if (! $fromNumber || blank($messageText)) {
            Log::warning('WhatsApp webhook: gagal mengurai nomor/pesan dari payload — lihat raw_body di log sebelumnya untuk tahu nama field yang sebenarnya dikirim gateway.');

            // Webhook verifikasi/ping dari gateway sering mengirim body
            // kosong atau field berbeda -- dibalas 200 supaya gateway
            // tidak menganggapnya gagal & mencoba berulang.
            return response('OK', 200);
        }

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

        // Bot dipanggil APA ADANYA -- sama persis logika yang dipakai
        // widget web (termasuk otomatis diam kalau admin sudah pernah
        // membalas percakapan ini).
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
