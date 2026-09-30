<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_campaign_id')->nullable()->constrained()->nullOnDelete();
            // Token unik yang disimpan di cookie pengunjung -- dipakai
            // AffiliateReferralService untuk mencocokkan klik ini dengan
            // registrasi client baru yang terjadi belakangan.
            $table->uuid('cookie_token')->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_fingerprint', 128)->nullable();
            $table->string('landing_url')->nullable();
            $table->timestamps();

            $table->index('affiliate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_clicks');
    }
};
