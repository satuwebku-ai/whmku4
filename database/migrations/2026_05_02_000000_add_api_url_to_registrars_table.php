<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrars', function (Blueprint $table) {
            // Base URL API — dipakai provider yang endpoint-nya berbeda tiap akun
            // (mis. Liqu.id yang di-deploy per-registrar). Namecheap tidak
            // memakai ini karena URL-nya tetap (sandbox/production).
            $table->string('api_url')->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('registrars', function (Blueprint $table) {
            $table->dropColumn('api_url');
        });
    }
};
