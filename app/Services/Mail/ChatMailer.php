<?php

namespace App\Services\Mail;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Mengirim balasan staf pada percakapan channel email ke alamat email
 * pengunjung/klien. Token [CHAT-id] di subjek membuat balasan mereka
 * kembali ke percakapan yang sama (lihat InboundMailProcessor).
 */
class ChatMailer
{
    public static function sendReply(ChatConversation $chat, ChatMessage $message): bool
    {
        if (! $chat->email || (blank($message->message) && ! $message->attachment_path)) {
            return false;
        }

        $site = Setting::get('site_name', config('app.name'));
        $body = trim((string) $message->message) . "\n\n--\n{$site}\nBalas email ini untuk melanjutkan percakapan.";

        try {
            Mail::raw($body, function ($mail) use ($chat, $message, $site) {
                $mail->to($chat->email, $chat->display_name)
                    ->subject("Balasan dari {$site} [CHAT-{$chat->id}]");

                if ($message->attachment_path && Storage::disk('local')->exists($message->attachment_path)) {
                    $mail->attachData(
                        Storage::disk('local')->get($message->attachment_path),
                        $message->attachment_name ?: 'lampiran',
                        ['mime' => $message->attachment_mime ?: 'application/octet-stream'],
                    );
                }
            });

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
