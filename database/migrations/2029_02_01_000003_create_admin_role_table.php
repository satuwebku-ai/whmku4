<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setara `role_user` di blueprint. Dinamai `admin_role` (bukan
     * `role_user`) karena project ini TIDAK punya tabel `users` terpadu
     * dengan kolom `user_type` -- Admin & Client sudah lebih dulu jadi dua
     * model/tabel terpisah (guard `admin` & `client`) jauh sebelum
     * blueprint ini ditulis. Role di blueprint ditujukan untuk staf
     * internal, jadi pivot ini disambungkan ke `admins`, bukan
     * direkayasa memaksa Client ikut punya role.
     */
    public function up(): void
    {
        Schema::create('admin_role', function (Blueprint $table) {
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['admin_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role');
    }
};
