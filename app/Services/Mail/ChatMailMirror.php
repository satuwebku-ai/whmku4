<?php

namespace App\Services\Mail;

use App\Models\ActivityLog;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pengunjung yang memilih jalur "Email" di widget chat ditangani lewat
 * Inbox Email: pesannya menjadi thread email, dan balasan staf dari Inbox
 * Email dikirim ke email pengunjung SEKALIGUS muncul di widget mereka.
 */
class ChatMailMirror
{
    public static function isEmailChat(ChatConversation $chat): bool
    {
        return $chat->channel === 'email' && filled($chat->email);
    }

    /**
     * Pesan pengunjung (widget) -> surat masuk di Inbox Email.
     */
    public static function fromVisitor(ChatConversation $chat, ChatMessage $message): MailThread
    {
        $email = strtolower(trim((string) $chat->email));
        $thread = MailThread::where('chat_conversation_id', $chat->id)->first();
        $isNew = ! $thread;

        if ($isNew) {
            $thread = MailThread::create([
                'subject' => 'Live Chat — ' . $chat->display_name,
                'contact_email' => $email,
                'contact_name' => $chat->display_name,
                'client_id' => $chat->client_id,
                'chat_conversation_id' => $chat->id,
                'status' => 'open',
                'last_message_at' => now(),
            ]);
        } elseif ($thread->status === 'closed') {
            $thread->update(['status' => 'open']);
        }

        $attachments = null;

        if ($message->attachment_path && Storage::disk('local')->exists($message->attachment_path)) {
            // Disalin, supaya menghapus thread tidak merusak lampiran di riwayat chat.
            $ext = strtolower(pathinfo((string) $message->attachment_name, PATHINFO_EXTENSION) ?: 'bin');
            $path = 'mail/' . Str::random(40) . '.' . $ext;
            Storage::disk('local')->copy($message->attachment_path, $path);

            $attachments = [[
                'path' => $path,
                'name' => (string) $message->attachment_name,
                'mime' => $message->attachment_mime ?: 'application/octet-stream',
                'size' => Storage::disk('local')->size($path),
            ]];
        }

        $thread->messages()->create([
            'direction' => 'in',
            'from_email' => $email,
            'from_name' => $chat->display_name,
            'to_email' => (string) (Setting::get('imap_username') ?: config('mail.from.address')),
            'subject' => 'Live Chat',
            'body' => filled($message->message) ? $message->message : '(lampiran)',
            'attachments' => $attachments,
        ]);

        $thread->increment('unread_count');
        $thread->update(['last_message_at' => now()]);

        if ($isNew) {
            ActivityLog::record(
                'ticket',
                'Email baru dari ' . $chat->display_name,
                Str::limit($message->message ?: 'Mengirim lampiran', 80),
                route('admin.mail.show', $thread),
                'warning',
                $chat->client_id,
            );
        }

        return $thread;
    }

    /**
     * Balasan staf (atau email balasan pengunjung) pada thread yang
     * terhubung ke widget -> ikut tampil sebagai pesan di widget.
     */
    public static function toWidget(MailThread $thread, string $sender, string $body, ?int $adminId = null): void
    {
        $chat = $thread->chatConversation;

        if (! $chat) {
            return;
        }

        $chat->messages()->create([
            'sender' => $sender,
            'admin_id' => $adminId,
            'message' => Str::limit($body, 2000, ''),
        ]);

        $chat->update(['last_message_at' => now(), 'status' => 'open']);

        if (in_array($sender, ['admin', 'bot'], true)) {
            $chat->increment('unread_for_user');
        }
    }
}
