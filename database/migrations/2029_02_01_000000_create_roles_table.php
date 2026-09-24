<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel roles untuk RBAC custom (tanpa Spatie), sesuai Master
     * Blueprint. Ini lapisan TAMBAHAN di atas kolom `admins.role` (string)
     * & `admins.permissions` (json per-modul) yang sudah ada dan tetap
     * dipertahankan apa adanya — lihat catatan di app/Models/Role.php
     * untuk alasan keduanya sengaja dijalankan berdampingan, bukan saling
     * menggantikan.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            // Role sistem (mis. superadmin) tidak boleh dihapus lewat UI.
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
