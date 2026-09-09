<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Memeriksa apakah semua tabel yang dibutuhkan aplikasi sudah ada.
 *
 * Berguna setelah menyalin versi baru: kalau ada migrasi yang belum
 * dijalankan, halaman terkait akan error 500 dengan pesan SQL yang
 * membingungkan. Perintah ini menemukannya lebih dulu.
 */
class CheckSetup extends Command
{
    protected $signature = 'lumora:check';

    protected $description = 'Periksa kelengkapan tabel database dan konfigurasi dasar';

    /**
     * Tabel => modul yang membutuhkannya.
     */
    private const REQUIRED_TABLES = [
        'admins'             => 'Login admin (Fase 1)',
        'clients'            => 'Klien (Fase 2)',
        'orders'             => 'Order (Fase 2)',
        'invoices'           => 'Invoice (Fase 2)',
        'hosting_accounts'   => 'Hosting Account (Fase 2)',
        'servers'            => 'Server cPanel/WHM (Fase 3)',
        'registrars'         => 'Registrar domain (Fase 4)',
        'tlds'               => 'TLD Pricing (Fase 4)',
        'domains'            => 'Domain (Fase 4)',
        'payment_gateways'   => 'Payment Gateway (Fase 5)',
        'payments'           => 'Transaksi pembayaran (Fase 5)',
        'tickets'            => 'Support Ticket (Fase 6a)',
        'ticket_replies'     => 'Balasan tiket (Fase 6a)',
        'pages'              => 'Halaman CMS (Fase 6b)',
        'announcements'      => 'Pengumuman (Fase 6b)',
        'settings'           => 'Pengaturan (Fase 6b)',
        'product_categories' => 'Kategori produk / katalog (Fase 7)',
        'products'           => 'Produk hosting (Fase 7)',
        'invoice_items'      => 'Rincian invoice (Fase 7)',
        'coupons'            => 'Kupon diskon',
        'activity_logs'      => 'Log aktivitas & notifikasi admin',
        'chat_conversations' => 'Live chat',
        'login_attempts'     => 'Catatan percobaan login',
        'nav_menus'          => 'Menu navigasi publik',
        'chat_messages'      => 'Pesan live chat',
        'client_balance_logs' => 'Buku besar saldo klien',
        'coupon_product' => 'Kupon per produk',
        'coupon_product_category' => 'Kupon per kategori',
        'notification_templates' => 'Template notifikasi',
        'domain_documents' => 'Dokumen persyaratan domain Indonesia',
        'promo_banners' => 'Banner promo publik',
        'addons' => 'Katalog addon',
        'hosting_account_addons' => 'Addon terpasang per layanan',
    ];

    /**
     * Kolom penting yang ditambahkan lewat migrasi susulan.
     */
    private const REQUIRED_COLUMNS = [
        'tlds'     => ['cost_register', 'cost_renew', 'cost_transfer', 'show_in_search', 'is_demo'],
        'payment_gateways' => ['qris_method_code'],
        'admins'   => ['two_factor_enabled'],
        'clients'  => ['internal_notes', 'whatsapp_number', 'notify_promo', 'notify_whatsapp', 'google_id', 'avatar', 'balance'],
        'hosting_accounts' => ['renewal_invoice_id', 'product_id', 'pending_upgrade_product_id', 'pending_upgrade_invoice_id', 'client_details'],
        'domains' => ['renewal_invoice_id', 'is_transfer', 'transfer_auth_code', 'eligibility_criteria', 'eligibility_extra', 'documents_verified_at', 'privacy_invoice_id', 'privacy_expires_at'],
        'invoices' => ['is_topup'],
        'orders'   => ['internal_notes'],
        'domains'  => ['internal_notes'],
    ];

    public function handle(): int
    {
        $missingTables = [];
        $missingColumns = [];

        foreach (self::REQUIRED_TABLES as $table => $label) {
            if (! Schema::hasTable($table)) {
                $missingTables[] = [$table, $label];
            }
        }

        foreach (self::REQUIRED_COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $missingColumns[] = [$table, $column];
                }
            }
        }

        if ($missingTables) {
            $this->error('Tabel berikut belum ada:');
            $this->table(['Tabel', 'Dibutuhkan oleh'], $missingTables);
        }

        if ($missingColumns) {
            $this->error('Kolom berikut belum ada:');
            $this->table(['Tabel', 'Kolom'], $missingColumns);
        }

        if ($missingTables || $missingColumns) {
            $this->newLine();
            $this->warn('Jalankan: php artisan migrate');
            $this->warn('Lalu:    php artisan optimize:clear');

            return self::FAILURE;
        }

        $this->info('Semua tabel dan kolom yang dibutuhkan sudah tersedia.');

        // Email tidak membuat aplikasi gagal jalan, tapi diam-diam
        // melumpuhkan OTP dan reset password — jadi diperiksa juga.
        if (config('mail.default') === 'log') {
            $this->newLine();
            $this->warn('MAIL_MAILER masih "log" — email tidak benar-benar dikirim.');
            $this->warn('Akibatnya: kode OTP admin dan reset password klien tidak akan sampai.');
            $this->warn('Isi konfigurasi SMTP di .env, lalu uji: php artisan lumora:test-mail email@kamu.com');
        } elseif (blank(config('mail.from.address'))) {
            $this->newLine();
            $this->warn('MAIL_FROM_ADDRESS belum diisi — banyak server SMTP akan menolak pengiriman.');
        }

        // Peringatan non-fatal yang sering terlewat saat deploy.
        if (! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
            $this->warn('Symlink storage belum dibuat — lampiran tiket tidak akan bisa diakses.');
            $this->warn('Jalankan: php artisan storage:link');
        }

        return self::SUCCESS;
    }
}
