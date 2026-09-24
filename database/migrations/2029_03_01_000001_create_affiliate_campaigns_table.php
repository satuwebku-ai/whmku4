<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link tracking bernama milik seorang affiliate (mis. "Promo IG
     * Agustus"), boleh override tarif komisi default affiliate itu
     * sendiri untuk campaign tertentu.
     */
    public function up(): void
    {
        Schema::create('affiliate_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('landing_url')->nullable();
            $table->string('code')->unique();
            $table->enum('commission_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('commission_value', 12, 2)->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->unsignedInteger('clicks_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_campaigns');
    }
};
