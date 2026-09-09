<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable(); // ringkasan 1 baris untuk kartu produk
            $table->text('description')->nullable();
            $table->json('features')->nullable(); // array string, satu fitur per baris di form

            // Harga per siklus tagihan — nullable berarti siklus itu tidak
            // ditawarkan untuk produk ini (mis. tidak jual opsi bulanan).
            $table->decimal('price_monthly', 12, 2)->nullable();
            $table->decimal('price_quarterly', 12, 2)->nullable();
            $table->decimal('price_semi_annually', 12, 2)->nullable();
            $table->decimal('price_annually', 12, 2)->nullable();
            $table->decimal('setup_fee', 12, 2)->default(0);

            // required : wajib pilih/daftarkan domain untuk order produk ini
            // optional : boleh pakai domain sendiri atau tanpa domain
            // none     : produk tidak terkait domain (mis. add-on, lisensi)
            $table->enum('domain_option', ['required', 'optional', 'none'])->default('optional');

            // Data provisioning default — dipakai Fase 7c saat order dibuat
            // otomatis jadi HostingAccount lalu di-provision via Fase 3.
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('panel_package')->nullable(); // nama plan di WHM/cPanel

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('stock')->nullable(); // null = tidak dibatasi
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
