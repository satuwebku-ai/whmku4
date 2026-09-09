<?php

namespace App\Services\Chat;

use App\Models\AiChatUsage;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Balasan otomatis di live chat -- provider AI-nya (Claude, ChatGPT,
 * dst) ditentukan lewat AiProviderFactory, jadi class ini sendiri
 * TIDAK PEDULI provider mana yang dipakai. Menambah provider baru
 * tidak menyentuh file ini sama sekali.
 *
 * Sengaja SINKRON (dipanggil langsung dalam permintaan HTTP saat
 * pengunjung kirim pesan, bukan lewat antrean/queue worker) --
 * konsisten dengan keputusan arsitektur chat ini sendiri (polling,
 * bukan WebSocket) karena shared hosting umumnya tidak mengizinkan
 * proses latar belakang yang jalan terus-menerus.
 */
class AiChatService
{
    public function enabled(): bool
    {
        if (Setting::get('ai_chat_enabled', '0') !== '1') {
            return false;
        }

        // Kunci API yang dicek beda per provider -- provider yang
        // sedang tidak dipakai boleh kosong tanpa masalah.
        $provider = Setting::get('ai_chat_provider', 'anthropic');
        $key = $provider === 'openai' ? 'ai_chat_openai_api_key' : 'ai_chat_api_key';

        return filled(Setting::get($key));
    }

    /**
     * Hasilkan balasan bot untuk percakapan ini, lalu SIMPAN sebagai
     * ChatMessage (sender=bot) -- supaya pemanggilnya (ChatController)
     * tidak perlu tahu detail provider yang dipakai, cukup panggil
     * lalu percakapan sudah terupdate.
     *
     * Return null kalau bot tidak seharusnya membalas (nonaktif, sudah
     * ditangani admin, atau API gagal) -- percakapan tetap berjalan
     * normal tanpa balasan bot, TIDAK melempar error ke pengunjung.
     */
    public function reply(ChatConversation $conversation): ?ChatMessage
    {
        if (! $this->enabled()) {
            return null;
        }

        // Begitu admin manapun pernah membalas, bot berhenti otomatis --
        // staf manusia sudah mengambil alih, bot tidak boleh menimpa.
        if ($conversation->messages()->where('sender', 'admin')->exists()) {
            return null;
        }

        $history = $conversation->messages()
            ->whereIn('sender', ['user', 'bot'])
            ->orderBy('id')
            ->limit(20) // cukup untuk konteks, tanpa membengkakkan biaya token per pesan
            ->get();

        if ($history->isEmpty()) {
            return null;
        }

        $messages = $history->map(fn ($m) => [
            'role' => $m->sender === 'bot' ? 'assistant' : 'user',
            'content' => $m->message ?: '(mengirim lampiran/berkas)',
        ])->values()->all();

        $provider = AiProviderFactory::make();
        $providerKey = Setting::get('ai_chat_provider', 'anthropic');
        $model = Setting::get("ai_chat_model_{$providerKey}") ?: $provider->defaultModel();

        try {
            $result = $provider->chat($messages, $this->systemPrompt(), $model);
        } catch (Throwable $e) {
            Log::warning('AI chat bot: provider melempar exception — ' . $e->getMessage());

            return null;
        }

        if (! $result['success'] || blank($result['text'])) {
            if (! $result['success']) {
                Log::warning('AI chat bot: gagal mendapat balasan — ' . $result['message']);
            }

            return null;
        }

        // Token dicatat APA ADANYA dari respons provider -- ini yang
        // membuat halaman pemakaian di admin akurat, bukan perkiraan.
        try {
            AiChatUsage::create([
                'chat_conversation_id' => $conversation->id,
                'model' => $model,
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
            ]);
        } catch (Throwable $e) {
            // Pencatatan pemakaian gagal TIDAK boleh menggagalkan
            // balasan bot itu sendiri -- cukup dicatat ke log.
            Log::warning('AI chat bot: gagal mencatat pemakaian token — ' . $e->getMessage());
        }

        $message = $conversation->messages()->create([
            'sender' => 'bot',
            'message' => $result['text'],
        ]);

        $conversation->increment('unread_for_user');
        $conversation->update(['last_message_at' => now()]);

        return $message;
    }

    /**
     * Konteks bisnis untuk bot -- diatur admin (Pengaturan → Live
     * Chat), BUKAN ditulis tetap di kode, karena tiap bisnis hosting
     * beda produk/kebijakan/harga. Tanpa ini diisi, bot akan menjawab
     * generik tanpa tahu apa-apa soal layanan yang sebenarnya dijual.
     * Sama dipakai untuk provider manapun.
     */
    private function systemPrompt(): string
    {
        $konteks = Setting::get('ai_chat_context', '');
        $namaSitus = Setting::get('site_name', 'layanan hosting ini');

        $dasar = "Anda adalah asisten chat otomatis untuk {$namaSitus}, sebuah penyedia layanan hosting & VPS.\n\n"
            . "Aturan:\n"
            . "- Jawab singkat, ramah, dan dalam Bahasa Indonesia.\n"
            . "- Kalau tidak yakin atau pertanyaannya di luar kemampuan Anda (butuh akses akun, pembatalan, refund, atau masalah teknis mendalam), katakan dengan jujur bahwa staf manusia akan segera membantu -- jangan mengarang jawaban.\n"
            . "- Jangan pernah meminta atau menyebutkan password, nomor kartu, OTP, atau data sensitif lain.\n"
            . "- Jangan menjanjikan harga/diskon yang tidak disebutkan dalam konteks di bawah.";

        if (filled($konteks)) {
            $dasar .= "\n\nInformasi tentang bisnis ini (dari admin):\n" . $konteks;
        }

        return $dasar;
    }
}
