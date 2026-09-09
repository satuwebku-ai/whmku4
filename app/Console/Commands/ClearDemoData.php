<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Page;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tld;
use Illuminate\Console\Command;

/**
 * Menghapus data contoh bawaan DemoDataSeeder.
 *
 * Sengaja hanya menyasar record yang memang dibuat seeder (dicocokkan
 * lewat email/slug/nama spesifik), bukan menghapus seluruh isi tabel —
 * supaya data asli yang sudah kamu masukkan tidak ikut hilang.
 */
class ClearDemoData extends Command
{
    protected $signature = 'lumora:clear-demo
                            {--clients : Hapus klien contoh beserta order/invoice/layanannya}
                            {--tlds : Hapus TLD contoh (.com .net .id .co.id)}
                            {--gateway : Hapus gateway "Transfer Bank (Manual)" contoh}
                            {--pages : Hapus halaman contoh (Tentang Kami, S&K, Kebijakan Privasi)}
                            {--catalog : Hapus kategori & produk contoh (Shared Hosting, VPS, dst.)}
                            {--all : Hapus semua data contoh di atas}
                            {--force : Lewati konfirmasi}';

    protected $description = 'Hapus data contoh (demo) yang dibuat DemoDataSeeder';

    private const DEMO_CLIENT_EMAILS = [
        'budi.santoso@example.com',
        'siti.aminah@example.com',
        'admin@majujaya.co.id',
        'andi.wijaya@example.com',
        'dewi.lestari@example.com',
    ];

    private const DEMO_TLDS = ['.com', '.net', '.id', '.co.id'];

    private const DEMO_PAGE_SLUGS = ['tentang-kami', 'syarat-ketentuan', 'kebijakan-privasi'];

    private const DEMO_CATEGORY_SLUGS = ['shared-hosting', 'vps'];

    public function handle(): int
    {
        $all = $this->option('all');

        $doClients = $all || $this->option('clients');
        $doTlds    = $all || $this->option('tlds');
        $doGateway = $all || $this->option('gateway');
        $doPages   = $all || $this->option('pages');
        $doCatalog = $all || $this->option('catalog');

        if (! $doClients && ! $doTlds && ! $doGateway && ! $doPages && ! $doCatalog) {
            $this->warn('Tidak ada yang dipilih. Contoh pemakaian:');
            $this->line('  php artisan lumora:clear-demo --clients');
            $this->line('  php artisan lumora:clear-demo --all');
            $this->newLine();
            $this->line('Opsi: --clients --tlds --gateway --pages --catalog --all --force');

            return self::INVALID;
        }

        // ── Tampilkan dulu apa yang akan dihapus ──
        $this->info('Data contoh yang akan dihapus:');
        $this->newLine();

        $clients = $doClients
            ? Client::whereIn('email', self::DEMO_CLIENT_EMAILS)->withCount(['orders', 'invoices', 'hostingAccounts'])->get()
            : collect();

        if ($doClients) {
            if ($clients->isEmpty()) {
                $this->line('  Klien contoh: <fg=gray>tidak ditemukan</>');
            } else {
                $this->line("  Klien contoh: <fg=yellow>{$clients->count()} klien</>");
                foreach ($clients as $c) {
                    $this->line("    - {$c->name} ({$c->orders_count} order, {$c->invoices_count} invoice, {$c->hosting_accounts_count} layanan)");
                }
                $this->line('    <fg=red>Order, invoice, layanan, domain, tiket, dan pembayaran milik mereka ikut terhapus.</>');
            }
        }

        $tlds = $doTlds ? Tld::whereIn('extension', self::DEMO_TLDS)->get() : collect();

        if ($doTlds) {
            $this->line($tlds->isEmpty()
                ? '  TLD contoh: <fg=gray>tidak ditemukan</>'
                : "  TLD contoh: <fg=yellow>{$tlds->implode('extension', ', ')}</>");
        }

        $gateways = $doGateway
            ? PaymentGateway::where('driver', 'manual')->where('name', 'Transfer Bank (Manual)')->get()
            : collect();

        if ($doGateway) {
            $this->line($gateways->isEmpty()
                ? '  Gateway contoh: <fg=gray>tidak ditemukan</>'
                : '  Gateway contoh: <fg=yellow>Transfer Bank (Manual)</>');
        }

        $pages = $doPages ? Page::whereIn('slug', self::DEMO_PAGE_SLUGS)->get() : collect();

        if ($doPages) {
            $this->line($pages->isEmpty()
                ? '  Halaman contoh: <fg=gray>tidak ditemukan</>'
                : "  Halaman contoh: <fg=yellow>{$pages->implode('title', ', ')}</>");
            $this->line('    <fg=gray>Catatan: kalau isinya sudah kamu ubah jadi konten asli, jangan hapus ini.</>');
        }

        $categories = $doCatalog ? ProductCategory::whereIn('slug', self::DEMO_CATEGORY_SLUGS)->withCount('products')->get() : collect();

        if ($doCatalog) {
            if ($categories->isEmpty()) {
                $this->line('  Katalog contoh: <fg=gray>tidak ditemukan</>');
            } else {
                $totalProducts = $categories->sum('products_count');
                $this->line("  Katalog contoh: <fg=yellow>{$categories->count()} kategori, {$totalProducts} produk</> ({$categories->implode('name', ', ')})");
            }
        }

        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Lanjutkan penghapusan? Tindakan ini tidak bisa dibatalkan.', false)) {
            $this->comment('Dibatalkan. Tidak ada data yang dihapus.');

            return self::SUCCESS;
        }

        // ── Eksekusi ──
        $deleted = 0;

        foreach ($clients as $client) {
            // Relasi memakai cascadeOnDelete di migration, jadi order/invoice/
            // layanan/domain/tiket miliknya ikut terhapus otomatis.
            $client->delete();
            $deleted++;
        }

        foreach ($tlds as $tld) {
            if ($tld->domains()->exists()) {
                $this->warn("  TLD {$tld->extension} dilewati — masih dipakai domain aktif.");
                continue;
            }
            $tld->delete();
            $deleted++;
        }

        foreach ($gateways as $gateway) {
            if ($gateway->payments()->exists()) {
                $this->warn("  Gateway {$gateway->name} dilewati — sudah punya riwayat pembayaran.");
                continue;
            }
            $gateway->delete();
            $deleted++;
        }

        foreach ($pages as $page) {
            $page->delete();
            $deleted++;
        }

        foreach ($categories as $category) {
            // Produk di dalamnya ikut terhapus otomatis (cascadeOnDelete).
            $productCount = $category->products()->count();
            $category->delete();
            $deleted += 1 + $productCount;
        }

        $this->newLine();
        $this->info("Selesai. {$deleted} record data contoh dihapus.");

        return self::SUCCESS;
    }
}
