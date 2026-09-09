<?php

namespace App\Console\Commands;

use App\Models\Tld;
use Illuminate\Console\Command;

/**
 * Menyiapkan SATU contoh TLD di TLD Pricing supaya alur pemesanan bisa
 * dicoba sendiri dari awal sampai akhir: cari domain → masukkan keranjang
 * → checkout → bayar → layanan aktif.
 *
 * Kenapa memakai ".test":
 *
 * ".test" adalah TLD yang DICADANGKAN oleh RFC 2606 khusus untuk pengujian.
 * TLD itu tidak dijual siapa pun dan tidak akan pernah ada di internet,
 * jadi tidak mungkin bentrok dengan domain milik orang lain. Karena bukan
 * TLD sungguhan, ia juga tidak punya server RDAP — itulah sebabnya ditandai
 * `is_demo` supaya pengecekan ketersediaannya dilewati.
 *
 * Registrar sengaja dikosongkan, sehingga saat invoice lunas sistem TIDAK
 * memanggil API registrar sama sekali. Domainnya cukup tercatat sebagai
 * aktif di database.
 */
class DemoTld extends Command
{
    protected $signature = 'lumora:demo-tld
                            {--remove : Hapus TLD demo}';

    protected $description = 'Buat satu contoh TLD (.test) untuk mencoba alur pemesanan sendiri';

    private const EXT = '.test';

    public function handle(): int
    {
        if ($this->option('remove')) {
            $deleted = Tld::where('extension', self::EXT)->where('is_demo', true)->delete();

            $this->info($deleted ? 'TLD demo dihapus.' : 'TLD demo tidak ditemukan.');

            return self::SUCCESS;
        }

        $tld = Tld::updateOrCreate(
            ['extension' => self::EXT],
            [
                // Tanpa registrar → provisioning otomatis dilewati.
                'registrar_id'   => null,

                'cost_register'  => 50000,
                'cost_renew'     => 50000,
                'cost_transfer'  => 50000,
                'cost_currency'  => 'IDR',

                'register_price' => 75000,
                'renew_price'    => 75000,
                'transfer_price' => 75000,

                'min_years'      => 1,
                'max_years'      => 5,

                'is_active'      => true,
                'show_in_search' => true,
                'search_group'   => 'Demo',
                'search_order'   => 0,
                'is_demo'        => true,
            ]
        );

        $this->info('Contoh TLD siap dipakai.');
        $this->newLine();
        $this->table(
            ['Kolom', 'Nilai'],
            [
                ['Ekstensi', $tld->extension],
                ['Harga modal', 'Rp ' . number_format($tld->cost_register, 0, ',', '.')],
                ['Harga jual', 'Rp ' . number_format($tld->register_price, 0, ',', '.')],
                ['Margin', 'Rp ' . number_format($tld->margin, 0, ',', '.') . ' (' . $tld->margin_percent . '%)'],
                ['Registrar', '— (sengaja kosong)'],
                ['Grup', $tld->search_group],
            ]
        );

        $this->newLine();
        $this->line('Coba alurnya sendiri:');
        $this->line('  1. Buka halaman depan → ketik nama apa pun, mis. "tokosaya"');
        $this->line('  2. Centang ekstensi .test (bertanda DEMO), klik Cari Domain');
        $this->line('  3. Klik Tambah → domain masuk keranjang');
        $this->line('  4. Checkout → daftar/login sebagai klien → Buat Pesanan');
        $this->line('  5. Bayar lewat gateway Transfer Manual');
        $this->line('  6. Di admin: Pembayaran → Setujui & Lunasi');
        $this->line('  7. Cek Domain di admin dan di Client Area — statusnya aktif');
        $this->newLine();
        $this->comment('.test adalah TLD cadangan RFC 2606 — tidak dijual siapa pun,');
        $this->comment('jadi tidak menyentuh domain asli. Registrar dikosongkan, jadi');
        $this->comment('tidak ada panggilan ke Liqu.id saat invoice lunas.');
        $this->newLine();
        $this->comment('Hapus kapan saja: php artisan lumora:demo-tld --remove');

        return self::SUCCESS;
    }
}
