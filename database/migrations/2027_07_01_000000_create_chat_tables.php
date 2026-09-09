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

            $table->enum('status', ['open', 'closed'])->default('open');

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
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
