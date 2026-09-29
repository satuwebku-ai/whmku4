<?php

namespace Database\Seeders;

use App\Models\Addon;
use Illuminate\Database\Seeder;

/**
 * Katalog Lisensi: sertifikat SSL (dengan harga dari daftar harga) dan lisensi
 * software hosting (draf, belum berharga & belum aktif).
 *
 * Aman dijalankan berulang (idempotent):
 *  - keterangan (nama, merek, fitur, spesifikasi, FAQ) selalu diperbarui;
 *  - harga, status aktif, dan urutan HANYA diisi saat produk pertama kali dibuat,
 *    jadi perubahan harga yang sudah Anda lakukan di admin tidak tertimpa.
 *
 *   php artisan db:seed --class=AddonCatalogSeeder
 */
class AddonCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_merge($this->sslProducts(), $this->licenseProducts()) as $i => $item) {
            $addon = Addon::firstOrNew(['slug' => $item['slug']]);
            $isNew = ! $addon->exists;

            $addon->fill([
                'name' => $item['name'],
                'category' => $item['category'],
                'brand' => $item['brand'],
                'summary' => $item['summary'],
                'description' => $item['description'],
                'long_description' => $item['long_description'] ?? null,
                'features' => $item['features'],
                'specs' => $item['specs'],
                'faqs' => $item['faqs'],
            ]);

            if ($isNew) {
                $addon->fill([
                    'price_annually' => $item['price_annually'] ?? null,
                    'is_active' => $item['is_active'] ?? true,
                    'is_public' => true,
                    'pricing_source' => 'manual',
                    'sort_order' => ($item['category'] === 'ssl' ? 10 : 100) + $i,
                ]);
            }

            $addon->save();
        }
    }

    private function sslFaqs(string $validation, bool $wildcard): array
    {
        $faqs = [
            ['q' => 'Apa beda sertifikat DV, OV, dan EV?', 'a' => 'DV hanya memverifikasi kepemilikan domain. OV memverifikasi domain sekaligus identitas organisasi. EV adalah tingkat verifikasi organisasi paling ketat. Ketiganya sama-sama mengenkripsi koneksi HTTPS.'],
            ['q' => 'Apakah harga perpanjangan sama dengan harga registrasi?', 'a' => 'Ya. Harga registrasi dan perpanjangan produk ini sama, sehingga tidak ada kenaikan harga di tahun berikutnya.'],
            ['q' => 'Bagaimana proses setelah pembayaran?', 'a' => 'Anda akan mengisi data CSR dan menyelesaikan validasi sesuai jenis sertifikat, lalu sertifikat diterbitkan untuk dipasang di server. Tim dukungan siap membantu lewat tiket dan live chat jika ada kendala saat CSR atau instalasi.'],
            ['q' => 'Bagaimana dengan masa berlaku sertifikat?', 'a' => 'Produk ini dijual per tahun. Batas masa berlaku sertifikat mengikuti aturan CA/Browser Forum yang berlaku saat penerbitan, dan aturan tersebut dapat berubah. Detail penerbitan ulang akan dikonfirmasi saat proses order.'],
        ];
        if ($wildcard) {
            $faqs[] = ['q' => 'Subdomain apa saja yang tercakup wildcard?', 'a' => 'Satu sertifikat wildcard mencakup domain utama dan seluruh subdomain pada satu tingkat, misalnya blog.contoh.com dan toko.contoh.com. Subdomain bertingkat seperti a.b.contoh.com tidak tercakup.'];
        } else {
            $faqs[] = ['q' => 'Apakah sertifikat ini mencakup subdomain?', 'a' => 'Tidak. Sertifikat ini untuk satu nama domain. Untuk banyak subdomain sekaligus, pilih produk Wildcard.'];
        }
        if ($validation !== 'DV') {
            $faqs[] = ['q' => 'Dokumen apa yang diperlukan?', 'a' => 'Umumnya dokumen legalitas usaha (misalnya NIB atau akta pendirian) dan nomor telepon perusahaan yang dapat diverifikasi. Penerbit dapat meminta dokumen tambahan.'];
        }

        return $faqs;
    }

    private function sslProducts(): array
    {
        return [
            [
                'slug' => 'comodo-positive-ssl', 'category' => 'ssl', 'brand' => 'Sectigo',
                'name' => 'Comodo Positive SSL',
                'summary' => 'SSL Domain Validation untuk satu domain, cepat terbit dan terjangkau.',
                'description' => 'Sertifikat SSL DV dari Sectigo (Comodo) untuk satu nama domain. Cocok untuk blog, company profile, dan toko online yang butuh HTTPS dengan proses cepat tanpa dokumen perusahaan.',
                'price_annually' => 85000,
                'features' => [
                    'Enkripsi 256-bit untuk melindungi data pengunjung',
                    'Validasi domain saja, tanpa dokumen perusahaan',
                    'Umumnya terbit dalam hitungan menit setelah validasi domain selesai',
                    'Kompatibel dengan browser dan perangkat modern',
                    'Site seal dari penerbit untuk menambah kepercayaan pengunjung',
                ],
                'specs' => [
                    'Merek' => 'Sectigo (Comodo)', 'Tipe validasi' => 'Domain Validation (DV)',
                    'Cakupan domain' => '1 nama domain', 'Wildcard' => 'Tidak',
                    'Waktu penerbitan' => 'Beberapa menit setelah validasi domain',
                    'Verifikasi organisasi' => 'Tidak diperlukan', 'Garansi penerbit' => 'Hingga USD 10.000',
                    'Periode' => '1 tahun', 'Registrasi & perpanjangan' => 'Harga sama',
                ],
                'faqs' => $this->sslFaqs('DV', false),
            ],
            [
                'slug' => 'comodo-positive-ssl-wildcard', 'category' => 'ssl', 'brand' => 'Sectigo',
                'name' => 'Comodo Positive SSL Wildcard',
                'summary' => 'Satu sertifikat DV untuk domain utama dan semua subdomain satu tingkat.',
                'description' => 'Sertifikat SSL Wildcard DV dari Sectigo (Comodo). Satu sertifikat mengamankan domain utama beserta subdomain tak terbatas pada satu tingkat, misalnya www, blog, mail, dan toko.',
                'price_annually' => 1650000,
                'features' => [
                    'Mengamankan domain utama dan subdomain satu tingkat tanpa batas jumlah',
                    'Satu sertifikat dan satu perpanjangan untuk banyak layanan',
                    'Validasi domain saja, tanpa dokumen perusahaan',
                    'Enkripsi 256-bit dan kompatibel dengan browser modern',
                    'Hemat dibanding membeli SSL satu per satu jika subdomain banyak',
                ],
                'specs' => [
                    'Merek' => 'Sectigo (Comodo)', 'Tipe validasi' => 'Domain Validation (DV)',
                    'Cakupan domain' => 'Domain utama + subdomain satu tingkat (*.domain.com)', 'Wildcard' => 'Ya',
                    'Waktu penerbitan' => 'Beberapa menit setelah validasi domain',
                    'Verifikasi organisasi' => 'Tidak diperlukan', 'Garansi penerbit' => 'Hingga USD 10.000',
                    'Periode' => '1 tahun', 'Registrasi & perpanjangan' => 'Harga sama',
                ],
                'faqs' => $this->sslFaqs('DV', true),
            ],
            [
                'slug' => 'geotrust-true-business-id-ov', 'category' => 'ssl', 'brand' => 'GeoTrust',
                'name' => 'True Business ID OV',
                'summary' => 'SSL Organization Validation dengan identitas perusahaan terverifikasi.',
                'description' => 'Sertifikat SSL OV dari GeoTrust untuk satu domain. Identitas organisasi Anda diverifikasi penerbit dan tercantum di detail sertifikat, cocok untuk situs bisnis, layanan online, dan halaman login pelanggan.',
                'price_annually' => 1900000,
                'features' => [
                    'Identitas perusahaan diverifikasi dan tercantum di detail sertifikat',
                    'Meningkatkan kepercayaan pelanggan pada situs bisnis dan halaman transaksi',
                    'Enkripsi kuat untuk formulir login, pembayaran, dan data pelanggan',
                    'Dynamic site seal dari GeoTrust',
                    'Merek penerbit yang dikenal luas di kalangan bisnis',
                ],
                'specs' => [
                    'Merek' => 'GeoTrust', 'Tipe validasi' => 'Organization Validation (OV)',
                    'Cakupan domain' => '1 nama domain', 'Wildcard' => 'Tidak',
                    'Waktu penerbitan' => 'Umumnya 1 sampai 3 hari kerja (menunggu verifikasi organisasi)',
                    'Verifikasi organisasi' => 'Diperlukan', 'Garansi penerbit' => 'Hingga USD 1.250.000',
                    'Periode' => '1 tahun', 'Registrasi & perpanjangan' => 'Harga sama',
                ],
                'faqs' => $this->sslFaqs('OV', false),
            ],
            [
                'slug' => 'geotrust-true-business-id-wildcard-ov', 'category' => 'ssl', 'brand' => 'GeoTrust',
                'name' => 'True Business ID Wildcard OV',
                'summary' => 'SSL Wildcard OV: identitas perusahaan terverifikasi untuk semua subdomain.',
                'description' => 'Sertifikat SSL Wildcard OV dari GeoTrust. Mengamankan domain utama dan subdomain satu tingkat, sekaligus menampilkan identitas organisasi yang telah diverifikasi penerbit.',
                'price_annually' => 5800000,
                'features' => [
                    'Satu sertifikat untuk domain utama dan subdomain satu tingkat',
                    'Identitas perusahaan diverifikasi dan tercantum di detail sertifikat',
                    'Cocok untuk perusahaan dengan banyak layanan, portal, dan aplikasi pada subdomain berbeda',
                    'Dynamic site seal dari GeoTrust',
                    'Pengelolaan perpanjangan lebih sederhana dibanding banyak sertifikat terpisah',
                ],
                'specs' => [
                    'Merek' => 'GeoTrust', 'Tipe validasi' => 'Organization Validation (OV)',
                    'Cakupan domain' => 'Domain utama + subdomain satu tingkat (*.domain.com)', 'Wildcard' => 'Ya',
                    'Waktu penerbitan' => 'Umumnya 1 sampai 3 hari kerja (menunggu verifikasi organisasi)',
                    'Verifikasi organisasi' => 'Diperlukan', 'Garansi penerbit' => 'Hingga USD 1.250.000',
                    'Periode' => '1 tahun', 'Registrasi & perpanjangan' => 'Harga sama',
                ],
                'faqs' => $this->sslFaqs('OV', true),
            ],
            [
                'slug' => 'geotrust-true-business-id-ev', 'category' => 'ssl', 'brand' => 'GeoTrust',
                'name' => 'True Business ID EV',
                'summary' => 'SSL Extended Validation dengan tingkat verifikasi organisasi tertinggi.',
                'description' => 'Sertifikat SSL EV dari GeoTrust untuk satu domain. Penerbit melakukan verifikasi organisasi paling ketat, cocok untuk bank, fintech, e-commerce besar, dan layanan yang menangani data sensitif.',
                'price_annually' => 2600000,
                'features' => [
                    'Tingkat verifikasi organisasi paling ketat (Extended Validation)',
                    'Identitas perusahaan yang terverifikasi tampil di detail sertifikat',
                    'Cocok untuk layanan keuangan, pembayaran, dan data sensitif',
                    'Dynamic site seal dari GeoTrust',
                    'Memberi sinyal kepercayaan tertinggi bagi pelanggan dan mitra',
                ],
                'specs' => [
                    'Merek' => 'GeoTrust', 'Tipe validasi' => 'Extended Validation (EV)',
                    'Cakupan domain' => '1 nama domain', 'Wildcard' => 'Tidak (EV tidak mendukung wildcard)',
                    'Waktu penerbitan' => 'Umumnya beberapa hari kerja (verifikasi lebih ketat)',
                    'Verifikasi organisasi' => 'Diperlukan (ketat)', 'Garansi penerbit' => 'Hingga USD 1.500.000',
                    'Periode' => '1 tahun', 'Registrasi & perpanjangan' => 'Harga sama',
                ],
                'faqs' => $this->sslFaqs('EV', false),
            ],
        ];
    }

    /**
     * Lisensi software hosting. Belum ada daftar harga, jadi dibuat sebagai DRAF
     * (nonaktif, tanpa harga). Isi harga di Admin > Addons lalu aktifkan.
     */
    private function licenseProducts(): array
    {
        $make = fn (string $slug, string $name, string $brand, string $summary, string $description, array $features, array $specs) => [
            'slug' => $slug, 'category' => 'license', 'brand' => $brand, 'name' => $name,
            'summary' => $summary, 'description' => $description, 'features' => $features, 'specs' => $specs,
            'is_active' => false,
            'faqs' => [
                ['q' => 'Bagaimana lisensi ini diaktifkan?', 'a' => 'Setelah pembayaran terkonfirmasi, lisensi diproses dan informasi aktivasi dikirim melalui panel client. Tim dukungan siap membantu jika ada kendala.'],
                ['q' => 'Apakah lisensi terikat pada satu server?', 'a' => 'Umumnya lisensi terikat pada satu alamat IP atau server. Detail ketentuan penggunaan mengikuti kebijakan penerbit lisensi.'],
            ],
        ];

        return [
            $make('cpanel-whm', 'cPanel & WHM', 'cPanel', 'Panel kontrol hosting paling populer untuk server Linux.', 'Lisensi cPanel & WHM untuk mengelola akun hosting, domain, email, dan database dari satu panel yang mudah dipakai.', ['Antarmuka cPanel untuk pengguna dan WHM untuk administrator server', 'Manajemen domain, email, database, dan file', 'Dukungan backup dan restore akun', 'Ekosistem plugin dan integrasi luas'], ['Jenis' => 'Panel kontrol hosting', 'Platform' => 'Server Linux', 'Ikatan lisensi' => 'Per server / IP']),
            $make('softaculous', 'Softaculous Auto Installer', 'Softaculous', 'Instal WordPress dan ratusan aplikasi web dengan satu klik.', 'Lisensi Softaculous agar pelanggan hosting dapat memasang aplikasi web populer secara otomatis tanpa konfigurasi manual.', ['Instalasi satu klik untuk WordPress dan aplikasi populer lainnya', 'Staging, klon, dan backup situs', 'Pembaruan aplikasi yang lebih mudah', 'Terintegrasi dengan panel hosting'], ['Jenis' => 'Auto installer', 'Platform' => 'cPanel, DirectAdmin, Plesk dan lainnya', 'Ikatan lisensi' => 'Per server / IP']),
            $make('litespeed-web-server', 'LiteSpeed Web Server', 'LiteSpeed', 'Web server berkinerja tinggi untuk situs yang lebih cepat.', 'Lisensi LiteSpeed Web Server sebagai pengganti Apache dengan kinerja lebih baik, terutama untuk situs WordPress.', ['Kinerja tinggi dan penggunaan resource lebih efisien', 'Kompatibel dengan konfigurasi Apache', 'Dukungan LSCache untuk mempercepat WordPress', 'Cocok untuk server hosting dengan banyak situs'], ['Jenis' => 'Web server', 'Platform' => 'Server Linux', 'Ikatan lisensi' => 'Per server']),
            $make('cloudlinux-os', 'CloudLinux OS', 'CloudLinux', 'Isolasi akun untuk hosting bersama yang lebih stabil dan aman.', 'Lisensi CloudLinux OS yang membatasi resource tiap akun sehingga satu situs bermasalah tidak mengganggu situs lain di server yang sama.', ['Isolasi resource CPU, memori, dan I/O per akun', 'Meningkatkan stabilitas server hosting bersama', 'Fitur keamanan tambahan antar akun', 'Terintegrasi dengan panel hosting populer'], ['Jenis' => 'Sistem operasi server', 'Platform' => 'Server Linux', 'Ikatan lisensi' => 'Per server']),
            $make('imunify360', 'Imunify360', 'CloudLinux', 'Perlindungan keamanan server dari malware dan serangan web.', 'Lisensi Imunify360 untuk memindai malware, memblokir serangan, dan menjaga server hosting tetap aman.', ['Pemindaian dan pembersihan malware', 'Firewall dan perlindungan serangan web', 'Deteksi aktivitas mencurigakan', 'Terintegrasi dengan panel hosting'], ['Jenis' => 'Keamanan server', 'Platform' => 'Server Linux', 'Ikatan lisensi' => 'Per server']),
            $make('jetbackup', 'JetBackup', 'JetApps', 'Backup dan restore akun hosting yang mudah dan fleksibel.', 'Lisensi JetBackup untuk backup otomatis akun hosting dengan restore yang bisa dilakukan sendiri oleh pelanggan.', ['Backup terjadwal untuk akun hosting', 'Restore mandiri oleh pelanggan', 'Mendukung tujuan penyimpanan jarak jauh', 'Terintegrasi dengan panel hosting'], ['Jenis' => 'Backup', 'Platform' => 'cPanel, DirectAdmin, Plesk', 'Ikatan lisensi' => 'Per server']),
            $make('plesk', 'Plesk', 'Plesk', 'Panel kontrol hosting untuk server Linux maupun Windows.', 'Lisensi Plesk untuk mengelola situs web, email, dan database, tersedia untuk server Linux dan Windows.', ['Antarmuka modern untuk pengelolaan situs', 'Mendukung Linux dan Windows', 'Ekstensi untuk keamanan, backup, dan performa', 'Cocok untuk agensi dan pengelola beberapa situs'], ['Jenis' => 'Panel kontrol hosting', 'Platform' => 'Linux dan Windows', 'Ikatan lisensi' => 'Per server']),
            $make('directadmin', 'DirectAdmin', 'DirectAdmin', 'Panel kontrol hosting ringan dan efisien untuk server Linux.', 'Lisensi DirectAdmin, panel kontrol yang ringan dan efisien untuk mengelola akun hosting, domain, dan email.', ['Ringan dan hemat resource server', 'Manajemen domain, email, dan database', 'Tiga tingkat akses: admin, reseller, dan pengguna', 'Mendukung berbagai auto installer'], ['Jenis' => 'Panel kontrol hosting', 'Platform' => 'Server Linux', 'Ikatan lisensi' => 'Per server / IP']),
        ];
    }
}
