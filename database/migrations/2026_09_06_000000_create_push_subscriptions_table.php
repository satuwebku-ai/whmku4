<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris = satu browser/perangkat yang berlangganan push notification.
 * Satu klien/admin bisa punya beberapa baris (laptop + HP + browser
 * beda) -- karena itu polymorphic & bukan kolom langsung di
 * clients/admins.
 *
 * `endpoint` unik per baris: kalau browser yang sama subscribe ulang
 * (mis. re-install PWA), endpoint biasanya sama -- di-upsert, bukan
 * dobel. `p256dh` & `auth_token` adalah kunci enkripsi pesan push milik
 * PushSubscription browser (lihat WebPush\Subscription), bukan
 * kredensial akun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subscribable'); // subscribable_type + subscribable_id (Client atau Admin)
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique(); // sha256(endpoint) -- endpoint aslinya bisa >191 char, tidak muat kalau di-unique-kan langsung di banyak driver DB
            $table->string('p256dh');
            $table->string('auth_token');
            $table->string('label')->nullable(); // ringkasan user-agent, buat ditampilkan ke user sendiri ("Chrome di Windows")
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
