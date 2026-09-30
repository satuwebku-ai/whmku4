<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar domain premium CUSTOM: nama-nama tertentu (mis. abner.id) dengan
 * harga modal masing-masing, diimpor dari Excel/CSV. Terpisah dari
 * tld_premiums (tingkat harga per jumlah karakter). Harga jual kosong = belum dijual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_premium_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->unique();
            $table->string('label', 63);
            $table->string('extension', 30);
            $table->unsignedTinyInteger('characters')->default(0);
            $table->string('age_label', 50)->nullable();
            $table->unsignedSmallInteger('years')->default(1);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('sell_price', 14, 2)->nullable();
            $table->decimal('renew_price', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->string('import_batch', 40)->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sell_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_premium_domains');
    }
};
