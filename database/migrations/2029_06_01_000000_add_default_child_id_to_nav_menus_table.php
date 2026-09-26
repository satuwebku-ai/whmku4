<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu Utama yang sudah punya Subnav (children) tetap boleh berupa
     * tautan langsung (bukan dropdown) di navbar publik -- misalnya
     * "Domain" punya Subnav "Cek Domain", "Domain Premium", "Transfer
     * Domain", tapi saat diklik langsung menuju "Cek Domain".
     *
     * default_child_id menyimpan Subnav mana yang jadi tujuan langsung
     * itu. Kalau kosong, perilaku lama tetap berlaku: kalau punya
     * Subnav -> dropdown, kalau tidak -> tautan sendiri (route/page/url).
     *
     * Kolom ini HANYA berarti untuk Menu Utama (parent_id null). Diisi
     * self-reference ke nav_menus supaya nilainya wajib salah satu
     * Subnav yang benar-benar ada.
     */
    public function up(): void
    {
        Schema::table('nav_menus', function (Blueprint $table) {
            $table->foreignId('default_child_id')
                ->nullable()
                ->after('page_id')
                ->constrained('nav_menus')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nav_menus', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_child_id');
        });
    }
};
