<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PANDI (pengelola domain .id) TIDAK mengizinkan WHOIS Privacy untuk
     * domain di bawah .id -- data pendaftar wajib bisa diverifikasi &
     * terbuka, beda dari gTLD internasional (.com, .net, dst.) yang
     * memang mengizinkan anonimisasi. Kolom ini memisahkan mana TLD yang
     * boleh ditawari ID Protection dan mana yang tidak, dicentang manual
     * per-TLD di halaman TLD Pricing.
     */
    public function up(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            $table->boolean('whois_privacy_eligible')->default(true)->after('show_in_search');
            // NULL = ikut harga global (Setting whois_privacy_price di
            // panel "Harga Add-On Domain") -- diisi angka cuma kalau mau
            // beda dari harga global untuk TLD ini secara spesifik.
            $table->decimal('whois_privacy_price', 12, 2)->nullable()->after('whois_privacy_eligible');
        });

        // Default aman: semua ekstensi turunan .id (diatur PANDI) langsung
        // dimatikan begitu kolomnya dibuat -- supaya tidak keburu terjual
        // ke klien sebelum admin sempat meninjau satu per satu.
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
        Schema::table('tlds', function (Blueprint $table) {
            $table->dropColumn(['whois_privacy_eligible', 'whois_privacy_price']);
        });
    }
};
