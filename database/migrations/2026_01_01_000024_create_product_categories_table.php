<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Jenis Produk" adalah data di database yang dikelola admin (bukan
        // daftar yang tertulis di kode). Tiap jenis punya "perilaku" (kind):
        //   hosting = server cPanel/WHM, tagihan invoice
        //   vps     = server VM/cloud, tagihan deposit/jam
        // Segmen URL katalog diambil dari slug jenis; semua aturan server dan
        // tagihan membaca kind lewat ProductType (tidak ada kolom type di grup).
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name');
            $table->string('kind', 20)->default('hosting'); // hosting | vps
            $table->string('icon', 50)->nullable();         // class Font Awesome, mis. fa-server
            $table->string('color', 7)->default('#4f46e5'); // warna tab di halaman Produk
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('product_type_id')->nullable()->constrained('product_types')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // nama ikon Font Awesome, mis. "fa-server"
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Jenis bawaan.
        $now = now();

        DB::table('product_types')->insert([
            [
                'slug' => 'hosting', 'name' => 'Hosting (cPanel/WHM)', 'kind' => 'hosting',
                'icon' => 'fa-server', 'color' => '#4f46e5', 'sort_order' => 1,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'slug' => 'vps', 'name' => 'VPS / Cloud Server', 'kind' => 'vps',
                'icon' => 'fa-cloud', 'color' => '#059669', 'sort_order' => 2,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_groups');
        Schema::dropIfExists('product_types');
    }
};
