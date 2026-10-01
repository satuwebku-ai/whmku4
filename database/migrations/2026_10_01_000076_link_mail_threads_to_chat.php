<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengunjung yang memilih jalur "Email" di widget chat: pesannya masuk
        // ke Inbox Email. Kolom ini menghubungkan thread email dengan
        // percakapan widget-nya, supaya balasan staf dari Inbox Email juga
        // muncul di widget pengunjung.
        Schema::table('mail_threads', function (Blueprint $table) {
            $table->foreignId('chat_conversation_id')->nullable()->after('client_id')
                ->constrained('chat_conversations')->nullOnDelete();
        });

        // Pindahkan percakapan kanal email yang sudah ada ke Inbox Email.
        $chats = DB::table('chat_conversations')->where('channel', 'email')->whereNotNull('email')->get();

        foreach ($chats as $chat) {
            $name = $chat->name ?: null;

            $threadId = DB::table('mail_threads')->insertGetId([
                'subject' => 'Live Chat — ' . ($name ?: $chat->email),
                'contact_email' => strtolower($chat->email),
                'contact_name' => $name,
                'client_id' => $chat->client_id,
                'chat_conversation_id' => $chat->id,
                'status' => $chat->status === 'closed' ? 'closed' : 'open',
                'unread_count' => (int) $chat->unread_for_admin,
                'last_message_at' => $chat->last_message_at,
                'created_at' => $chat->created_at,
                'updated_at' => now(),
            ]);

            $messages = DB::table('chat_messages')->where('chat_conversation_id', $chat->id)
                ->whereIn('sender', ['user', 'admin'])->orderBy('id')->get();

            foreach ($messages as $m) {
                $in = $m->sender === 'user';

                DB::table('mail_messages')->insert([
                    'mail_thread_id' => $threadId,
                    'direction' => $in ? 'in' : 'out',
                    'from_email' => $in ? strtolower($chat->email) : (string) config('mail.from.address'),
                    'from_name' => $in ? $name : null,
                    'to_email' => $in ? (string) config('mail.from.address') : strtolower($chat->email),
                    'subject' => 'Live Chat',
                    'body' => $m->message ?: '(lampiran)',
                    'admin_id' => $in ? null : ($m->admin_id ?? null),
                    'created_at' => $m->created_at,
                    'updated_at' => $m->created_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('mail_threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chat_conversation_id');
        });
    }
};
