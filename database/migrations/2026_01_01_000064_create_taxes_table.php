<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * invoices.tax sekarang cuma nominal manual per invoice -- tabel ini
     * katalog tarif pajak (mis. PPN 11%) untuk dipilih/dihitung otomatis
     * di masa depan. Belum di-wire ke perhitungan invoice manapun --
     * murni skema dulu, sesuai kesepakatan foundation-only untuk batch ini.
     */
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate_percentage', 5, 2);
            $table->string('country', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['country', 'is_active'], 'taxes_country_active_index');
        });

        // Invoices are created earlier in the timeline; add the optional
        // catalog reference after the tax table exists.
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('tax_id')->nullable()->after('discount')->constrained('taxes')->nullOnDelete();
            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['tax_id']);
            $table->dropColumn(['tax_id', 'tax_rate']);
        });

        Schema::dropIfExists('taxes');
    }
};
