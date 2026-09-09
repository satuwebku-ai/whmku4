<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alternatif pengisian kartu harga: alih-alih mengetik harga jual
     * tiap komponen satu per satu, admin cukup menentukan MARKUP PERSEN
     * di atas harga modal yang ditarik langsung dari API provider
     * (/pricing/policy).
     *
     * Keuntungannya: kalau provider menaikkan harga modal, harga jual
     * ikut menyesuaikan otomatis -- tidak perlu edit manual dan tidak
     * ada risiko diam-diam jual di bawah modal.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->enum('pricing_mode', ['manual', 'markup'])->default('manual')->after('max_accounts');
            $table->decimal('markup_percent', 6, 2)->nullable()->after('pricing_mode');
            $table->json('cost_cache')->nullable()->after('markup_percent');
            $table->timestamp('cost_cached_at')->nullable()->after('cost_cache');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'markup_percent', 'cost_cache', 'cost_cached_at']);
        });
    }
};
