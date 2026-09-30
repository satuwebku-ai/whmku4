<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Titik awal permission-nya mengikuti 7 modul yang sudah ada di
     * Admin::MODULES (sales, billing, services, infrastructure, support,
     * content, system) supaya konsisten dengan middleware `module:xxx`
     * yang sudah dipakai di seluruh routes/admin.php -- bukan dibuat
     * granular per-aksi dari nol, yang akan butuh menulis ulang puluhan
     * definisi route sekaligus.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
