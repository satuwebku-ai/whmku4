<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Console\Command;

/**
 * Percakapan yang masih 'open' tapi tidak ada aktivitas sama sekali
 * (dari klien MAUPUN staf) selama beberapa waktu ditutup otomatis --
 * supaya daftar Live Chat tidak menumpuk percakapan basi yang
 * sebenarnya sudah selesai tapi lupa ditutup manual.
 *
 * Pesan pemberitahuan otomatis dikirim TEPAT sebelum menutup, supaya
 * klien tahu kenapa percakapannya tertutup dan bisa mulai lagi kalau
 * masih ada pertanyaan.
 */
class CloseInactiveChats extends Command
{
    protected $signature = 'lumora:close-inactive-chats {--minutes=15 : Batas tidak aktif sebelum ditutup otomatis}';

    protected $description = 'Tutup otomatis percakapan live chat yang sudah lama tidak aktif.';

    public function handle(): int
    {
        ob_start();
        $result = $this->handleJob();
        $output = ob_get_clean();
        echo $output;

        \App\Models\CronJob::recordExecution('lumora:close-inactive-chats', $result === self::SUCCESS, $output);

        return $result;
    }

    private function handleJob(): int
    {
        $minutes = (int) $this->option('minutes');

        // Percakapan yang SEDANG dipegang admin (assigned_admin_id
        // terisi) DIKECUALIKAN dari penutupan otomatis -- kalau tidak,
        // admin yang sedang membaca riwayat klien atau mengetik balasan
        // panjang lebih dari $minutes bisa kehilangan percakapannya
        // begitu saja (tertutup otomatis padahal sedang aktif dilayani),
        // membingungkan klien yang sedang menunggu balasan.
        $stale = ChatConversation::open()
            ->whereNull('assigned_admin_id')
            ->where('last_message_at', '<', now()->subMinutes($minutes))
            ->get();

        if ($stale->isEmpty()) {
            $this->info('Tidak ada percakapan yang perlu ditutup otomatis.');

            return self::SUCCESS;
        }

        foreach ($stale as $chat) {
            $chat->messages()->save(new ChatMessage([
                'sender' => 'bot',
                'message' => 'Percakapan ini otomatis ditutup karena sudah tidak ada aktivitas. Silakan kirim pesan baru kapan saja kalau masih ada pertanyaan.',
            ]));

            $chat->update([
                'status' => 'closed',
                'unread_for_user' => $chat->unread_for_user + 1,
            ]);

            $this->line("  Ditutup: {$chat->display_name} (percakapan #{$chat->id})");
        }

        $this->newLine();
        $this->info("Selesai -- {$stale->count()} percakapan ditutup otomatis.");

        return self::SUCCESS;
    }
}
