<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Klien bisa punya lebih dari satu alamat (mis. alamat tagihan vs
     * alamat kantor cabang). Kolom address/city/dst di tabel clients
     * TETAP dipertahankan sebagai alamat utama/default -- tabel ini
     * untuk alamat TAMBAHAN, bukan pengganti.
     */
    public function up(): void
    {
        Schema::create('client_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Alamat');
            $table->string('recipient_name')->nullable();
            $table->string('phone')->nullable();
            $table->text('address');
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Indonesia');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_addresses');
    }
};
