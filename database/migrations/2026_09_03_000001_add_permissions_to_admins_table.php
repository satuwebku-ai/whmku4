<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom "permissions" menyimpan daftar modul (array JSON) yang boleh
 * diakses admin ini, mis. ["billing","support"]. NULL berarti belum
 * diatur manual oleh superadmin -> dipakai daftar bawaan sesuai peran
 * (lihat Admin::ROLE_DEFAULT_MODULES). Array kosong [] berarti sengaja
 * dikunci total dari semua modul.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
