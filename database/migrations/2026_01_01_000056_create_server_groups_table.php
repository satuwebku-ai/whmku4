<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // Cara memilih server saat order baru masuk: least_accounts | priority | round_robin
            $table->string('selection_mode', 30)->default('least_accounts');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Keanggotaan grup many-to-many: satu server boleh ada di beberapa
        // grup (mis. "Shared Hosting" dan "Promo"), dengan prioritas per grup.
        Schema::create('server_group_server', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_group_id')->constrained('server_groups')->cascadeOnDelete();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            // Angka kecil = didahulukan (dipakai mode "priority" dan pemutus seri).
            $table->unsignedSmallInteger('priority')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['server_group_id', 'server_id']);
        });

        // Keanggotaan grup HANYA lewat tabel pivot di atas. Database lama masih
        // punya kolom servers.server_group_id (tidak dipakai kode); isinya sudah
        // disalin ke pivot oleh App\Support\SchemaRepair.

        Schema::table('products', function (Blueprint $table) {
            // Kalau diisi, server dipilih otomatis dari grup ini (menggantikan server_id tunggal).
            $table->foreignId('server_group_id')->nullable()->after('server_id')
                ->constrained('server_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('server_group_id');
        });

        Schema::dropIfExists('server_group_server');
        Schema::dropIfExists('server_groups');
    }
};
