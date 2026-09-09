<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            // Harga MODAL dari registrar — dipisahkan dari harga JUAL
            // (register_price dkk). Sebelumnya markup membaca dan menulis
            // kolom yang sama, sehingga menjalankannya dua kali membuat
            // harga naik berlipat.
            $table->decimal('cost_register', 12, 2)->default(0)->after('registrar_id');
            $table->decimal('cost_renew', 12, 2)->default(0)->after('cost_register');
            $table->decimal('cost_transfer', 12, 2)->default(0)->after('cost_renew');
            $table->string('cost_currency', 3)->default('IDR')->after('cost_transfer');
            $table->timestamp('cost_synced_at')->nullable()->after('cost_currency');
        });

        // Data lama: harga yang terlanjur tersimpan di kolom jual sebenarnya
        // adalah harga modal hasil sinkronisasi, jadi disalin ke kolom modal.
        \Illuminate\Support\Facades\DB::table('tlds')
            ->where('register_price', '>', 0)
            ->update([
                'cost_register' => \Illuminate\Support\Facades\DB::raw('register_price'),
                'cost_renew'    => \Illuminate\Support\Facades\DB::raw('renew_price'),
                'cost_transfer' => \Illuminate\Support\Facades\DB::raw('transfer_price'),
            ]);
    }

    public function down(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            $table->dropColumn([
                'cost_register', 'cost_renew', 'cost_transfer',
                'cost_currency', 'cost_synced_at',
            ]);
        });
    }
};
