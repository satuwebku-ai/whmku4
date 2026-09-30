<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // Diskon otomatis untuk seluruh klien di grup ini, mis. grup
            // "Reseller" dapat potongan tetap di semua invoice mereka.
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('client_group_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_group_id');
        });

        Schema::dropIfExists('client_groups');
    }
};
