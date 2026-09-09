<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ekstensi yang tampil di halaman Cek Domain publik secara default.
     *
     * Menampilkan seluruh TLD (bisa ratusan) membuat halaman penuh sesak
     * dan mendorong pengunjung mencentang terlalu banyak sekaligus, yang
     * berujung timeout ke registrar. Jadi defaultnya sedikit dan bisa
     * diatur sendiri lewat TLD Pricing.
     */
    private const DEFAULT_VISIBLE = [
        '.com' => 'Populer',
        '.net' => 'Populer',
        '.org' => 'Populer',
        '.xyz' => 'Populer',
        '.site' => 'Populer',
        '.online' => 'Populer',
        '.store' => 'Bisnis',
        '.biz' => 'Bisnis',
        '.info' => 'Bisnis',
        '.co' => 'Bisnis',
        '.id' => 'Indonesia',
        '.co.id' => 'Indonesia',
        '.web.id' => 'Indonesia',
        '.my.id' => 'Indonesia',
        '.or.id' => 'Indonesia',
        '.sch.id' => 'Indonesia',
        '.ac.id' => 'Indonesia',
        '.tech' => 'Teknologi',
        '.dev' => 'Teknologi',
        '.cloud' => 'Teknologi',
        '.app' => 'Teknologi',
    ];

    public function up(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            $table->boolean('show_in_search')->default(false)->after('is_active');
            $table->string('search_group')->nullable()->after('show_in_search');
            $table->unsignedInteger('search_order')->default(0)->after('search_group');
        });

        foreach (self::DEFAULT_VISIBLE as $ext => $group) {
            DB::table('tlds')->where('extension', $ext)->update([
                'show_in_search' => true,
                'search_group' => $group,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            $table->dropColumn(['show_in_search', 'search_group', 'search_order']);
        });
    }
};
