<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel standar Laravel untuk channel 'database' pada Notification
     * class (berbeda dari activity_logs yang sudah ada -- ini format
     * baku Laravel: id UUID, type, notifiable polymorphic, data JSON,
     * read_at). Belum ada Notification class manapun yang memakai
     * channel 'database' saat ini (semua lewat mail/custom), tabel ini
     * cuma disiapkan supaya siap dipakai kapan saja tanpa migration baru.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->morphs('notifiable');
            $table->string('notification_type');
            $table->string('event_key')->nullable()->index();
            $table->string('dedupe_key', 64)->unique();
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'notification_type'], 'nd_notifiable_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
    }
};
