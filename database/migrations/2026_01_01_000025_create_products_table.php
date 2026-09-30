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
            $table->foreignId('product_category_id')->constrained('product_groups')->cascadeOnDelete();
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
            // Siklus tagihan custom (dalam hari) — cuma untuk produk
            // Hosting/VPS. custom_cycle_days menentukan berapa hari 1
            // siklus "Custom" itu (mis. 45 hari) — cuma bisa diisi
            // Superadmin (dijaga di controller, bukan di database).
            $table->decimal('price_custom', 12, 2)->nullable();
            $table->unsignedSmallInteger('custom_cycle_days')->nullable();
            $table->decimal('setup_fee', 12, 2)->default(0);

            // required : wajib pilih/daftarkan domain untuk order produk ini
            // optional : boleh pakai domain sendiri atau tanpa domain
            // none     : produk tidak terkait domain (mis. add-on, lisensi)
            $table->enum('domain_option', ['required', 'optional', 'none'])->default('optional');

            // Data provisioning default — dipakai Fase 7c saat order dibuat
            // otomatis jadi HostingAccount lalu di-provision via Fase 3.
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('panel_package', 500)->nullable(); // nama plan di WHM/cPanel; VPS: JSON spesifikasi

            // Khusus produk VPS: ditagih dari saldo per jam, atau invoice
            // berkala seperti hosting biasa.
            $table->enum('billing_mode', ['invoice', 'deposit'])->default('invoice');
            $table->enum('pricing_mode', ['manual', 'markup'])->nullable();
            $table->decimal('markup_percent', 6, 2)->nullable();
            $table->decimal('price_per_vcpu_hour', 12, 6)->nullable();
            $table->decimal('price_per_ram_gb_hour', 12, 6)->nullable();
            $table->decimal('price_per_storage_gb_hour', 12, 6)->nullable();
            $table->decimal('price_per_backup_gb_hour', 12, 6)->nullable();
            $table->decimal('price_per_snapshot_gb_hour', 12, 6)->nullable();
            $table->decimal('price_windows_license_per_vcpu_hour', 12, 6)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('stock')->nullable(); // null = tidak dibatasi
            $table->unsignedInteger('reserved_stock')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // product_option_groups dibuat sebelum products (2026_09_04), jadi
        // kolom product_id-nya dibuat tanpa FK saat itu -- constraint-nya
        // dipasang di sini, begitu products sudah ada.
        if (Schema::hasTable('product_option_groups')) {
            Schema::table('product_option_groups', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            });
        }

        // hosting_accounts & orders juga dibuat sebelum products, dengan
        // kolom product_id/pending_upgrade_product_id tanpa FK -- dipasang
        // di sini juga.
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('pending_upgrade_product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['pending_upgrade_product_id']);
        });

        if (Schema::hasTable('product_option_groups')) {
            Schema::table('product_option_groups', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
            });
        }

        Schema::dropIfExists('products');
    }
};
