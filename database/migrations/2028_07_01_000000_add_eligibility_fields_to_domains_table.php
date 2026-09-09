<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Beberapa TLD (.asia, .ca, .coop, .es, .jobs, .nl, .pro, .ru, .us)
     * mewajibkan data kelayakan (eligibility) tambahan dari registry
     * aslinya sebelum bisa didaftarkan — dikonfirmasi dari spesifikasi
     * resmi Liqu.id. Disimpan di sini supaya begitu admin mengisinya
     * sekali, sistem bisa langsung lanjut mendaftarkan tanpa perlu
     * diminta ulang.
     */
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('eligibility_criteria')->nullable()->after('provision_message');
            $table->string('eligibility_extra')->nullable()->after('eligibility_criteria');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['eligibility_criteria', 'eligibility_extra']);
        });
    }
};
