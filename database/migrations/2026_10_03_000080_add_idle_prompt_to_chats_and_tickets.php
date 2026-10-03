<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kapan bot menanyakan "mau lanjut atau tidak?". Kosong = belum ditanya.
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->timestamp('idle_prompted_at')->nullable()->after('last_message_at');
        });

        // Penanda jenis pesan bot, supaya widget bisa menampilkan tombol
        // pilihan (Lanjut / Tidak) hanya pada pertanyaan "mau lanjut?".
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('kind', 30)->nullable()->after('sender');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('idle_prompted_at')->nullable()->after('last_reply_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', fn (Blueprint $t) => $t->dropColumn('idle_prompted_at'));
        Schema::table('chat_messages', fn (Blueprint $t) => $t->dropColumn('kind'));
        Schema::table('tickets', fn (Blueprint $t) => $t->dropColumn('idle_prompted_at'));
    }
};
