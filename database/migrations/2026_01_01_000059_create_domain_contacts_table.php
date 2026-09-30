<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelumnya TIDAK ADA tempat menyimpan data kontak WHOIS sama sekali
     * -- pendaftaran domain langsung memakai data client apa adanya.
     * Tabel ini opsional: kalau kosong untuk sebuah domain, fallback ke
     * data client seperti sebelumnya (tidak mengubah alur registrasi
     * yang sudah ada).
     */
    public function up(): void
    {
        Schema::create('domain_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['registrant', 'admin', 'tech', 'billing']);
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('organization')->nullable();
            $table->string('email');
            $table->string('phone');
            $table->text('address');
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->default('ID');
            $table->timestamps();

            $table->unique(['domain_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_contacts');
    }
};
