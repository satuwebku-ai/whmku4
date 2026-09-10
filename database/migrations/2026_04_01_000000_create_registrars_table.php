<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrars', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // label internal, mis. "Namecheap - Utama"
            $table->string('provider')->default('namecheap'); // namecheap, resellbiz — provider lain menyusul
            // Base URL API — dipakai provider yang endpoint-nya berbeda
            // tiap akun (mis. Liqu.id yang di-deploy per-registrar).
            // Namecheap tidak memakai ini karena URL-nya tetap.
            $table->string('api_url')->nullable();
            $table->string('api_username');
            $table->text('api_key'); // dienkripsi
            $table->string('username')->nullable(); // Namecheap: UserName (biasanya sama dgn ApiUser)
            $table->string('client_ip')->nullable(); // Namecheap wajib whitelist IP client

            // Nameserver default per registrar -- dipakai otomatis saat
            // domain baru didaftarkan (kalau klien tidak menentukan
            // nameserver sendiri).
            $table->string('default_ns1')->nullable();
            $table->string('default_ns2')->nullable();

            // Harga default ID Protection per registrar -- NULL berarti
            // ikut harga global. Tingkatan PERTENGAHAN antara harga global
            // dan harga per-TLD:
            //   harga per-TLD (kalau diisi)
            //     -> harga per-registrar (kalau diisi)
            //       -> harga global (fallback terakhir)
            $table->decimal('whois_privacy_price', 12, 2)->nullable();

            // Alamat email bawaan untuk tombol "Kirim Dokumen ke Registrar"
            // di halaman detail domain -- opsional, admin tetap bisa
            // mengetik alamat lain secara manual saat mengirim.
            $table->string('documents_email')->nullable();

            $table->boolean('sandbox')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrars');
    }
};
