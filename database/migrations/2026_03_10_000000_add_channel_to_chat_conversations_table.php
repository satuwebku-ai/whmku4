<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membedakan percakapan dari widget web vs WhatsApp asli --
     * keduanya memakai tabel yang SAMA (chat_conversations/
     * chat_messages) supaya admin, AiChatService, dan seluruh UI kelola
     * chat yang sudah ada bisa dipakai ulang tanpa dibangun dua kali.
     */
    public function up(): void
    {
        // chat_conversations belum ada di titik ini -- baru dibuat
        // belakangan (2027_07_01). Logika ditunda ke
        // 2027_07_01_000002_catchup_deferred_chat_alterations.php
        if (Schema::hasTable('chat_conversations') && ! Schema::hasColumn('chat_conversations', 'channel')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->enum('channel', ['web', 'whatsapp'])->default('web')->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chat_conversations', 'channel')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->dropColumn('channel');
            });
        }
    }
};
