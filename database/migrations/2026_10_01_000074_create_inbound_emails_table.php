<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan setiap email masuk yang sudah diproses: mencegah email yang
        // sama masuk dua kali dan memudahkan penelusuran kalau ada yang hilang.
        Schema::create('inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 64)->unique();
            $table->string('message_id')->nullable();
            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->string('result', 120);          // ticket:12 | chat:5 | ignored:... | error:...
            $table->timestamps();
        });

        // Percakapan dari email memakai tabel chat yang sama. Enum lama hanya
        // mengenal web & whatsapp, jadi diubah ke string supaya 'email' valid.
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->string('channel', 20)->default('web')->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_emails');
    }
};
