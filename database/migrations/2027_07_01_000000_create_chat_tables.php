<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();

            // Pengunjung yang belum login dikenali lewat token acak yang
            // disimpan di session — supaya percakapannya tidak hilang saat
            // pindah halaman, tanpa perlu memaksa mereka mendaftar dulu.
            $table->string('guest_token', 64)->nullable()->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->enum('status', ['open', 'closed'])->default('open');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            // Menandai satu percakapan live chat SUDAH pernah dijadikan
            // tiket -- supaya tidak bisa dikonversi dua kali, dan supaya
            // halaman chat bisa menampilkan tautan langsung ke tiketnya.
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();

            // Membedakan percakapan dari widget web vs WhatsApp asli --
            // keduanya memakai tabel yang SAMA supaya admin, AiChatService,
            // dan seluruh UI kelola chat yang sudah ada bisa dipakai ulang.
            $table->enum('channel', ['web', 'whatsapp'])->default('web');

            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_for_admin')->default(0);
            $table->unsignedInteger('unread_for_user')->default(0);

            $table->string('page_url')->nullable();   // halaman saat chat dimulai
            $table->string('ip_address')->nullable();

            $table->timestamps();

            $table->index(['status', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();

            // bot   = pesan otomatis (sambutan/promo)
            // user  = pengunjung atau klien
            // admin = staf
            $table->enum('sender', ['bot', 'user', 'admin'])->default('user');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();

            $table->text('message')->nullable();

            // Lampiran, mis. bukti transfer.
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['chat_conversation_id', 'id']);
        });

        // ai_chat_usages dibuat sebelum chat_conversations (2026_03_09), jadi
        // kolom chat_conversation_id-nya dibuat tanpa FK saat itu --
        // constraint-nya dipasang di sini, begitu chat_conversations ada.
        if (Schema::hasTable('ai_chat_usages')) {
            Schema::table('ai_chat_usages', function (Blueprint $table) {
                $table->foreign('chat_conversation_id')->references('id')->on('chat_conversations')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_chat_usages')) {
            Schema::table('ai_chat_usages', function (Blueprint $table) {
                $table->dropForeign(['chat_conversation_id']);
            });
        }

        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
