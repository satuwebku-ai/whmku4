<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tlds', function (Blueprint $table) {
            $table->id();
            $table->string('extension')->unique(); // ".com", ".id", ".co.id"
            $table->foreignId('registrar_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('register_price', 12, 2)->default(0);
            $table->decimal('renew_price', 12, 2)->default(0);
            $table->decimal('transfer_price', 12, 2)->default(0);
            $table->unsignedTinyInteger('min_years')->default(1);
            $table->unsignedTinyInteger('max_years')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tlds');
    }
};
