<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_menus', function (Blueprint $table) {
            $table->id();
            $table->string('label');

            // route  = halaman bawaan sistem (Hosting, Domain, Pengumuman)
            // page   = tautan ke Halaman (tabel pages)
            // url    = tautan bebas ke mana saja, termasuk luar situs
            $table->enum('type', ['route', 'page', 'url'])->default('url');

            $table->string('route_name')->nullable();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url')->nullable();

            $table->boolean('open_in_new_tab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });

        // Menu yang sudah ada sekarang (Hosting, Domain, Pengumuman)
        // dipindahkan ke sini sebagai data awal, supaya situs yang sudah
        // jalan tidak kehilangan menunya begitu migrasi ini dijalankan.
        DB::table('nav_menus')->insert([
            ['label' => 'Hosting', 'type' => 'route', 'route_name' => 'catalog.index', 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Domain', 'type' => 'route', 'route_name' => 'domain.search', 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Pengumuman', 'type' => 'route', 'route_name' => 'announcements.index', 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_menus');
    }
};
