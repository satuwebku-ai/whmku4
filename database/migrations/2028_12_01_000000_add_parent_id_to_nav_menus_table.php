<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memungkinkan menu jadi dropdown — item dengan parent_id terisi
     * ditampilkan sebagai submenu di bawah menu induknya (mis. "Hosting"
     * punya submenu "Shared Hosting", "VPS", dst), bukan sejajar di
     * baris menu utama.
     */
    public function up(): void
    {
        Schema::table('nav_menus', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('nav_menus')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nav_menus', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
