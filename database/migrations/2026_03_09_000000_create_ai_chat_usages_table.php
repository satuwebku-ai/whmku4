<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan tiap panggilan API bot chat -- token dicatat APA ADANYA
     * dari respons Anthropic (akurat 100%), bukan diperkirakan.
     *
     * Biaya TIDAK dihitung & disimpan di sini secara tetap, karena
     * tarif per model bisa berubah dan berbeda antar sumber -- dihitung
     * saat DITAMPILKAN memakai tarif yang admin isi sendiri di
     * Pengaturan (lihat ai_chat_price_* di tabel settings), supaya
     * selalu bisa diperbarui tanpa migrasi baru tiap kali harga berubah.
     */
    public function up(): void
    {
        Schema::create('ai_chat_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('model', 100);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->timestamps();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_usages');
    }
};
