<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            // ssl | license -- dipakai untuk filter di halaman katalog Lisensi.
            $table->string('category', 30)->default('license')->after('slug')->index();
            $table->string('brand', 100)->nullable()->after('category');
            $table->string('summary', 255)->nullable()->after('brand');
            $table->text('long_description')->nullable()->after('description');
            // Daftar fitur (array string), spesifikasi (label => nilai), dan FAQ (array {q, a}).
            $table->json('features')->nullable()->after('long_description');
            $table->json('specs')->nullable()->after('features');
            $table->json('faqs')->nullable()->after('specs');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn(['category', 'brand', 'summary', 'long_description', 'features', 'specs', 'faqs']);
        });
    }
};
