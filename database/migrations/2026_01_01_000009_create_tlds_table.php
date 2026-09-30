<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ekstensi yang tampil di halaman Cek Domain publik secara default.
     *
     * Menampilkan seluruh TLD (bisa ratusan) membuat halaman penuh sesak
     * dan mendorong pengunjung mencentang terlalu banyak sekaligus, yang
     * berujung timeout ke registrar. Jadi defaultnya sedikit dan bisa
     * diatur sendiri lewat TLD Pricing.
     */
    private const DEFAULT_VISIBLE = [
        '.com' => 'Populer',
        '.net' => 'Populer',
        '.org' => 'Populer',
        '.xyz' => 'Populer',
        '.site' => 'Populer',
        '.online' => 'Populer',
        '.store' => 'Bisnis',
        '.biz' => 'Bisnis',
        '.info' => 'Bisnis',
        '.co' => 'Bisnis',
        '.id' => 'Indonesia',
        '.co.id' => 'Indonesia',
        '.web.id' => 'Indonesia',
        '.my.id' => 'Indonesia',
        '.or.id' => 'Indonesia',
        '.sch.id' => 'Indonesia',
        '.ac.id' => 'Indonesia',
        '.tech' => 'Teknologi',
        '.dev' => 'Teknologi',
        '.cloud' => 'Teknologi',
        '.app' => 'Teknologi',
    ];

    public function up(): void
    {
        Schema::create('tlds', function (Blueprint $table) {
            $table->id();
            $table->string('extension'); // ".com", ".id", ".co.id"
            $table->foreignId('registrar_id')->nullable()->constrained()->nullOnDelete();

            // Harga MODAL dari registrar — dipisahkan dari harga JUAL
            // (register_price dkk) supaya markup tidak membaca & menulis
            // kolom yang sama (menjalankannya dua kali membuat harga naik
            // berlipat).
            $table->decimal('cost_register', 12, 2)->default(0);
            $table->decimal('cost_renew', 12, 2)->default(0);
            $table->decimal('cost_transfer', 12, 2)->default(0);
            $table->string('cost_currency', 3)->default('IDR');
            // Harga MODAL per tahun (2-10 tahun), diisi otomatis saat
            // sinkronisasi dari registrar yang menyediakannya. Terpisah
            // dari year_prices/year_renew_prices (harga JUAL) di bawah.
            $table->json('cost_year_prices')->nullable();
            $table->json('cost_year_renew_prices')->nullable();
            $table->timestamp('cost_synced_at')->nullable();

            $table->decimal('register_price', 12, 2)->default(0);
            $table->decimal('renew_price', 12, 2)->default(0);
            $table->decimal('transfer_price', 12, 2)->default(0);
            // Harga khusus per durasi, dalam bentuk {"2": 380000, "3": 550000}.
            // Kosong berarti harga dihitung linier: harga_1_tahun × jumlah
            // tahun. Dipakai untuk memberi diskon pembelian jangka panjang.
            $table->json('year_prices')->nullable();
            $table->json('year_renew_prices')->nullable();
            $table->unsignedTinyInteger('min_years')->default(1);
            $table->unsignedTinyInteger('max_years')->default(10);
            $table->boolean('is_active')->default(true);

            // Ekstensi yang tampil di halaman Cek Domain publik secara
            // default -- diatur manual per-TLD di TLD Pricing supaya
            // halaman publik tidak penuh sesak oleh ratusan ekstensi.
            $table->boolean('show_in_search')->default(false);
            // TLD demo: dipakai untuk mencoba alur pemesanan dari awal
            // sampai akhir tanpa menyentuh registrar atau domain
            // sungguhan. Pengecekan ketersediaannya dilewati (selalu
            // dianggap tersedia) karena TLD cadangan seperti .test memang
            // tidak punya server RDAP.
            $table->boolean('is_demo')->default(false);
            $table->string('search_group')->nullable();
            $table->unsignedInteger('search_order')->default(0);

            // PANDI (pengelola domain .id) TIDAK mengizinkan WHOIS Privacy
            // untuk domain di bawah .id -- data pendaftar wajib bisa
            // diverifikasi & terbuka, beda dari gTLD internasional yang
            // memang mengizinkan anonimisasi.
            $table->boolean('whois_privacy_eligible')->default(true);
            // NULL = ikut harga global (Setting whois_privacy_price di
            // panel "Harga Add-On Domain").
            $table->decimal('whois_privacy_price', 12, 2)->nullable();

            $table->timestamps();

            // Unik per KOMBINASI (extension, registrar_id), BUKAN per
            // extension sendirian -- supaya kalau dua registrar
            // sama-sama menjual ".com" (mis. Liqu.id DAN DNAMA), keduanya
            // bisa hidup berdampingan sebagai baris terpisah, admin yang
            // pilih mana yang mau diaktifkan/dijual.
            $table->unique(['extension', 'registrar_id'], 'tlds_extension_registrar_unique');
        });

        // Ekstensi turunan .id (diatur PANDI) langsung dimatikan begitu
        // tabel dibuat -- supaya tidak keburu terjual ke klien sebelum
        // admin sempat meninjau satu per satu.
        DB::table('tlds')
            ->where(function ($q) {
                $q->where('extension', '.id')
                  ->orWhere('extension', 'like', '%.id')
                  ->orWhere('extension', 'like', '%.co.id')
                  ->orWhere('extension', 'like', '%.web.id')
                  ->orWhere('extension', 'like', '%.my.id')
                  ->orWhere('extension', 'like', '%.biz.id')
                  ->orWhere('extension', 'like', '%.or.id')
                  ->orWhere('extension', 'like', '%.net.id')
                  ->orWhere('extension', 'like', '%.sch.id')
                  ->orWhere('extension', 'like', '%.ac.id')
                  ->orWhere('extension', 'like', '%.go.id')
                  ->orWhere('extension', 'like', '%.desa.id');
            })
            ->update(['whois_privacy_eligible' => false]);

        foreach (self::DEFAULT_VISIBLE as $ext => $group) {
            DB::table('tlds')->where('extension', $ext)->update([
                'show_in_search' => true,
                'search_group' => $group,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tlds');
    }
};
