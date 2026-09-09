<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Susulan untuk 2026_09_01_000000_add_whois_privacy_eligible_to_tlds.
     *
     * Kolom whois_privacy_price ditambahkan ke file migration ITU
     * SETELAH migration-nya terlanjur dijalankan di server. Laravel
     * mencatat migration berdasarkan NAMA FILE, bukan isinya -- begitu
     * tercatat sudah jalan, perubahan isi file tidak akan pernah
     * dieksekusi lagi. Akibatnya kolomnya tidak pernah dibuat, dan
     * menyimpan harga ID Protection per-TLD gagal dengan
     * "Unknown column 'whois_privacy_price'".
     *
     * Dicek dulu dengan hasColumn() supaya aman dijalankan di database
     * yang sudah terlanjur punya kolomnya (mis. instalasi baru yang
     * migration-nya jalan setelah file itu diperbarui).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tlds', 'whois_privacy_price')) {
            Schema::table('tlds', function (Blueprint $table) {
                $table->decimal('whois_privacy_price', 12, 2)->nullable()->after('whois_privacy_eligible');
            });
        }

        if (! Schema::hasColumn('registrars', 'whois_privacy_price')) {
            Schema::table('registrars', function (Blueprint $table) {
                $table->decimal('whois_privacy_price', 12, 2)->nullable()->after('default_ns2');
            });
        }

        // Kolom harga modal per tahun -- sama kasusnya, ditambahkan
        // belakangan lewat migration terpisah yang bisa saja belum
        // sempat dijalankan.
        if (! Schema::hasColumn('tlds', 'cost_year_prices')) {
            Schema::table('tlds', function (Blueprint $table) {
                $table->json('cost_year_prices')->nullable()->after('cost_currency');
                $table->json('cost_year_renew_prices')->nullable()->after('cost_year_prices');
            });
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun: kolom-kolom ini "dimiliki"
        // migration aslinya masing-masing. Membatalkan di sini bisa
        // menghapus kolom yang masih dianggap ada oleh migration lain.
    }
};
