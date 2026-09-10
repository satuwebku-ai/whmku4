<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dipindah dari 2026_09_01_000000_add_whois_privacy_eligible_to_tlds.php
     * -- migrasi itu memakai ->after('show_in_search'), padahal kolom
     * show_in_search baru dibuat di file ini (2027_04_01).
     */
    public function up(): void
    {
        if (Schema::hasColumn('tlds', 'whois_privacy_eligible')) {
            return;
        }

        Schema::table('tlds', function (Blueprint $table) {
            $table->boolean('whois_privacy_eligible')->default(true)->after('show_in_search');
            $table->decimal('whois_privacy_price', 12, 2)->nullable()->after('whois_privacy_eligible');
        });

        DB::table('tlds')
            ->where(function ($q) {
                $q->where('extension', '.id')
                  ->orWhere('extension', 'like', '%.id')
                  ->orWhere('extension', 'like', '%.co.id')
                  ->orWhere('extension', 'like', '%.web.id')
                  ->orWhere('extension', 'like', '%.my.id')
                  ->orWhere('extension', 'like', '%.biz.id')
                  ->orWhere('extension', 'like', '%.or.id')
                  ->orWhere('extension', 'like', '%.net.id')
                  ->orWhere('extension', 'like', '%.sch.id')
                  ->orWhere('extension', 'like', '%.ac.id')
                  ->orWhere('extension', 'like', '%.go.id')
                  ->orWhere('extension', 'like', '%.desa.id');
            })
            ->update(['whois_privacy_eligible' => false]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('tlds', 'whois_privacy_eligible')) {
            Schema::table('tlds', function (Blueprint $table) {
                $table->dropColumn(['whois_privacy_eligible', 'whois_privacy_price']);
            });
        }
    }
};
