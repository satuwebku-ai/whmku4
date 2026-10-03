<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Penutupan otomatis live chat dalam DUA tahap:
 *
 *  1. Chat 'open' yang tidak ada aktivitas selama N menit -> bot bertanya
 *     "masih butuh bantuan? Lanjut / Tidak" (dengan tombol di widget).
 *  2. Kalau sesudah pertanyaan itu M menit tetap tidak ada pesan dari
 *     klien -> chat ditutup otomatis dengan pesan penutup.
 *
 * Klien yang menjawab "Lanjut" (atau mengirim pesan apa pun) membatalkan
 * penutupan; jawaban "Tidak, terima kasih" langsung menutup chat
 * (ditangani di ChatController::send).
 *
 * Chat yang SUDAH dipegang admin ikut diproses -- sebelumnya dikecualikan,
 * sehingga chat yang pernah dibalas admin tidak pernah tertutup sendiri.
 * Satu pengecualian: chat dipegang admin yang pesan terakhirnya dari KLIEN
 * (klien menunggu balasan admin) tidak ditutup, karena bolanya di admin.
 *
 * Pengaturan (tabel settings, ada default):
 *   chat_idle_prompt_minutes  -- default 10
 *   chat_idle_close_minutes   -- default 5
 */
class CloseInactiveChats extends Command
{
    protected $signature = 'lumora:close-inactive-chats
                            {--minutes= : Batas tidak aktif sebelum bot bertanya (menit)}
                            {--close-after= : Jeda sesudah pertanyaan sebelum ditutup (menit)}';

    protected $description = 'Tanya klien "mau lanjut?" lalu tutup otomatis live chat yang tidak aktif.';

    public function handle(): int
    {
        $promptAfter = max(1, (int) ($this->option('minutes') ?: Setting::get('chat_idle_prompt_minutes', 10)));
        $closeAfter = max(1, (int) ($this->option('close-after') ?: Setting::get('chat_idle_close_minutes', 5)));

        $prompted = $this->promptStale($promptAfter, $closeAfter);
        $closed = $this->closeUnanswered($closeAfter);

        $this->info("Selesai -- {$prompted} chat ditanya, {$closed} chat ditutup otomatis.");

        return self::SUCCESS;
    }

    private function promptStale(int $promptAfter, int $closeAfter): int
    {
        $stale = ChatConversation::open()
            ->inLiveChat()
            ->whereNull('idle_prompted_at')
            ->where('last_message_at', '<', now()->subMinutes($promptAfter))
            ->with('latestMessage')
            ->get();

        $count = 0;

        foreach ($stale as $chat) {
            $last = $chat->latestMessage;

            // Bola di tangan admin: klien baru bertanya dan admin sedang menangani.
            if ($chat->assigned_admin_id && $last && $last->sender === 'user') {
                continue;
            }

            $chat->messages()->save(new ChatMessage([
                'sender' => 'bot',
                'kind' => ChatMessage::KIND_IDLE_PROMPT,
                'message' => "Apakah Anda masih membutuhkan bantuan?\n"
                    . "Pilih \"Lanjut\" kalau masih ingin dilanjutkan. "
                    . "Kalau tidak ada balasan dalam {$closeAfter} menit, percakapan ini akan kami tutup otomatis.",
            ]));

            // last_message_at ikut maju supaya riwayat urut, tapi penutupan
            // memakai idle_prompted_at (bukan last_message_at).
            $chat->update([
                'idle_prompted_at' => now(),
                'last_message_at' => now(),
                'unread_for_user' => $chat->unread_for_user + 1,
            ]);

            $this->line("  Ditanya: {$chat->display_name} (percakapan #{$chat->id})");
            $count++;
        }

        return $count;
    }

    private function closeUnanswered(int $closeAfter): int
    {
        $due = ChatConversation::open()
            ->inLiveChat()
            ->whereNotNull('idle_prompted_at')
            ->where('idle_prompted_at', '<', now()->subMinutes($closeAfter))
            ->get();

        $count = 0;

        foreach ($due as $chat) {
            // Klien sempat membalas sesudah ditanya -> batalkan, jangan tutup.
            $replied = $chat->messages()
                ->where('sender', 'user')
                ->where('created_at', '>=', $chat->idle_prompted_at)
                ->exists();

            if ($replied) {
                $chat->update(['idle_prompted_at' => null]);

                continue;
            }

            $chat->messages()->save(new ChatMessage([
                'sender' => 'bot',
                'message' => 'Percakapan ini otomatis ditutup karena tidak ada balasan. Silakan kirim pesan baru kapan saja kalau masih ada pertanyaan.',
            ]));

            $chat->update([
                'status' => 'closed',
                'idle_prompted_at' => null,
                'unread_for_user' => $chat->unread_for_user + 1,
            ]);

            $this->line("  Ditutup: {$chat->display_name} (percakapan #{$chat->id})");
            $count++;
        }

        return $count;
    }
}
