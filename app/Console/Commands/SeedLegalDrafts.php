<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Sengaja dibuat sebagai command terpisah (bukan otomatis lewat migrasi/
 * seeder biasa) — supaya HANYA berjalan kalau admin sadar menjalankannya
 * sendiri, dan TIDAK diam-diam menimpa isi halaman yang mungkin sudah
 * pernah diedit manual sebelumnya.
 */
class SeedLegalDrafts extends Command
{
    protected $signature = 'lumora:seed-legal-drafts {--force : Timpa meski halaman sudah pernah diisi (bukan lagi placeholder)}';

    protected $description = 'Isi draf awal Syarat & Ketentuan + Kebijakan Privasi — BUKAN nasihat hukum final, wajib ditinjau ulang sebelum publik.';

    public function handle(): int
    {
        $site = Setting::get('site_name', config('app.name'));
        $force = $this->option('force');

        $this->warn('PENTING: Ini draf AWAL untuk titik mulai, bukan dokumen hukum final.');
        $this->warn('Tinjau ulang, sesuaikan dengan bisnismu, idealnya dicek profesional hukum');
        $this->warn('sebelum benar-benar dipublikasikan ke klien sungguhan.');
        $this->newLine();

        if (! $this->confirm('Lanjutkan mengisi draf ke halaman Syarat & Ketentuan dan Kebijakan Privasi?', true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $this->seedPage('syarat-ketentuan', 'Syarat & Ketentuan', $this->termsContent($site), $force);
        $this->seedPage('kebijakan-privasi', 'Kebijakan Privasi', $this->privacyContent($site), $force);

        $this->newLine();
        $this->info('Selesai. Buka Admin -> Konten & Halaman untuk membaca & menyunting lebih lanjut.');

        return self::SUCCESS;
    }

    private function seedPage(string $slug, string $title, string $content, bool $force): void
    {
        $page = Page::where('slug', $slug)->first();

        $looksLikePlaceholder = $page && (
            str_contains($page->content, 'Tuliskan syarat layanan') ||
            str_contains($page->content, 'Jelaskan bagaimana data pelanggan')
        );

        if ($page && ! $looksLikePlaceholder && ! $force) {
            $this->warn("Dilewati: halaman \"{$title}\" sudah pernah diisi (bukan placeholder lagi). Pakai --force kalau tetap mau menimpa.");

            return;
        }

        Page::updateOrCreate(['slug' => $slug], [
            'title' => $title,
            'content' => $content,
            'meta_title' => $title,
        ]);

        $this->info("Diisi: {$title}");
    }

    private function termsContent(string $site): string
    {
        return <<<HTML
        <p><em>Draf awal — tinjau dan sesuaikan sebelum dipublikasikan.</em></p>

        <h2>1. Ketentuan Umum</h2>
        <p>Dengan menggunakan layanan {$site}, Anda menyetujui syarat dan ketentuan berikut. {$site} berhak mengubah ketentuan ini sewaktu-waktu; perubahan akan diberitahukan lewat email atau pengumuman di situs.</p>

        <h2>2. Layanan yang Disediakan</h2>
        <p>{$site} menyediakan layanan hosting website, registrasi/pengelolaan domain, dan layanan pendukung terkait. Detail spesifikasi tiap paket tercantum di halaman produk masing-masing saat pemesanan.</p>

        <h2>3. Pembayaran & Penagihan</h2>
        <ul>
        <li>Invoice diterbitkan sesuai siklus tagihan yang dipilih (bulanan/tahunan/dst) dan wajib dibayar sebelum atau pada tanggal jatuh tempo.</li>
        <li>Layanan yang belum dibayar setelah masa tenggang akan disuspend sementara, dan diaktifkan kembali otomatis begitu pembayaran diterima.</li>
        <li>Perpanjangan otomatis akan diproses jika fitur auto-renew diaktifkan pada layanan terkait.</li>
        </ul>

        <h2>4. Domain</h2>
        <ul>
        <li>Registrasi domain diproses lewat mitra registrar resmi. Beberapa ekstensi domain (mis. .id, .co.id, .ac.id, dan sejenisnya) mewajibkan dokumen identitas/legalitas tambahan sesuai ketentuan pengelola domain terkait (PANDI untuk domain .id).</li>
        <li>Kegagalan menyerahkan dokumen yang diminta dalam waktu wajar dapat mengakibatkan pembatalan pendaftaran domain, dengan pengembalian dana sesuai kebijakan pengembalian dana di bawah.</li>
        <li>Persetujuan akhir pendaftaran domain merupakan kewenangan registrar/pengelola domain terkait, di luar kendali {$site}.</li>
        </ul>

        <h2>5. Kebijakan Pengembalian Dana (Refund)</h2>
        <p><em>[Isi sesuai kebijakan bisnis Anda yang sebenarnya — mis. berapa hari masa uji coba, biaya yang tidak bisa dikembalikan seperti biaya registrasi domain yang sudah diproses, dsb.]</em></p>

        <h2>6. Ketersediaan Layanan (Uptime)</h2>
        <p><em>[Isi sesuai jaminan uptime yang benar-benar bisa Anda penuhi — jangan janjikan angka yang tidak bisa dipertanggungjawabkan.]</em></p>

        <h2>7. Penggunaan yang Dilarang</h2>
        <p>Klien dilarang menggunakan layanan untuk aktivitas ilegal, spam, distribusi malware, pelanggaran hak cipta, atau aktivitas lain yang melanggar hukum yang berlaku di Indonesia.</p>

        <h2>8. Batasan Tanggung Jawab</h2>
        <p>{$site} tidak bertanggung jawab atas kerugian tidak langsung akibat gangguan layanan di luar kendali wajar kami, termasuk namun tidak terbatas pada gangguan pihak ketiga (registrar, penyedia infrastruktur, dsb).</p>

        <h2>9. Kontak</h2>
        <p><em>[Isi email/kontak resmi untuk pertanyaan terkait ketentuan ini.]</em></p>
        HTML;
    }

    private function privacyContent(string $site): string
    {
        return <<<HTML
        <p><em>Draf awal — tinjau dan sesuaikan sebelum dipublikasikan.</em></p>

        <h2>1. Data yang Kami Kumpulkan</h2>
        <ul>
        <li>Data akun: nama, email, nomor telepon/WhatsApp, alamat.</li>
        <li>Data pembayaran: dikelola lewat mitra payment gateway (Midtrans/Xendit/Duitku) — {$site} tidak menyimpan nomor kartu pembayaran secara langsung.</li>
        <li>Data domain: informasi kontak yang diwajibkan registrar/pengelola domain untuk keperluan WHOIS dan verifikasi (termasuk dokumen identitas untuk domain yang mewajibkannya).</li>
        <li>Data teknis: alamat IP, riwayat login, untuk keperluan keamanan (mis. deteksi percobaan login mencurigakan).</li>
        </ul>

        <h2>2. Penggunaan Data</h2>
        <p>Data digunakan untuk memproses pesanan, penagihan, dukungan pelanggan, dan kewajiban verifikasi yang diwajibkan pihak ketiga (mis. registrar domain). Kami tidak menjual data pelanggan kepada pihak ketiga untuk kepentingan pemasaran.</p>

        <h2>3. Berbagi Data dengan Pihak Ketiga</h2>
        <p>Data dibagikan seperlunya kepada: mitra registrar domain (untuk keperluan registrasi), mitra payment gateway (untuk pemrosesan pembayaran), dan penyedia infrastruktur hosting — sebatas yang diperlukan untuk menjalankan layanan yang Anda pesan.</p>

        <h2>4. Penyimpanan Dokumen Sensitif</h2>
        <p>Dokumen identitas yang diunggah untuk keperluan verifikasi domain (mis. KTP) disimpan dengan akses terbatas, tidak dapat diakses publik, dan hanya digunakan untuk proses verifikasi yang bersangkutan.</p>

        <h2>5. Hak Anda</h2>
        <p>Anda berhak meminta salinan, koreksi, atau penghapusan data pribadi Anda, sepanjang tidak bertentangan dengan kewajiban hukum yang mengharuskan kami menyimpan data tersebut (mis. data transaksi untuk keperluan pajak/pembukuan).</p>

        <h2>6. Keamanan Data</h2>
        <p>Kami menerapkan langkah keamanan wajar (enkripsi data sensitif, pembatasan akses berbasis peran) untuk melindungi data Anda, namun tidak ada sistem yang bisa menjamin keamanan 100%.</p>

        <h2>7. Kontak</h2>
        <p><em>[Isi email/kontak resmi untuk pertanyaan terkait privasi data.]</em></p>
        HTML;
    }
}
