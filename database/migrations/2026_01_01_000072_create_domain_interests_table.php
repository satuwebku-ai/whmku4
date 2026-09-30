<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 20); // search, cart, checkout
            $table->string('domain_name', 255);
            $table->foreignId('tld_id')->nullable()->nullOnDelete();
            $table->foreignId('tld_premium_id')->nullable()->nullOnDelete();
            $table->unsignedTinyInteger('years')->nullable();
            $table->string('source', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'event_type', 'created_at'], 'domain_interests_client_event_index');
            $table->index(['domain_name', 'event_type'], 'domain_interests_domain_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_interests');
    }
};