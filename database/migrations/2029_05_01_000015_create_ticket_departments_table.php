<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tickets.department sekarang cuma string bebas ('support', 'billing',
     * dst). Tabel ini katalog departemen yang bisa dikelola admin (nama,
     * urutan, aktif/tidak) -- kolom tickets.department TETAP dipertahankan
     * apa adanya, tabel ini opsional untuk pengelolaan daftar via UI.
     */
    public function up(): void
    {
        Schema::create('ticket_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_departments');
    }
};
