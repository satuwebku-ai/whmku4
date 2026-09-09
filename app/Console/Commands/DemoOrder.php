<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tld;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membuat satu contoh pesanan lengkap: klien → order → invoice → pembayaran
 * → layanan aktif, supaya alurnya bisa dilihat utuh tanpa menunggu pesanan
 * sungguhan masuk.
 *
 * PENTING — tidak ada yang menyentuh dunia luar:
 *  - Nama domain memakai "example.com" dan ".test", yaitu nama yang
 *    DICADANGKAN oleh RFC 2606 khusus untuk dokumentasi dan pengujian.
 *    Nama itu tidak bisa didaftarkan siapa pun, jadi mustahil bentrok
 *    dengan domain milik orang lain.
 *  - Tidak ada panggilan ke API registrar maupun cPanel. Semua ditandai
 *    sebagai manual/simulasi.
 *  - Tidak ada email yang dikirim.
 */
class DemoOrder extends Command
{
    protected $signature = 'lumora:demo-order {--fresh : Hapus data demo lama sebelum membuat yang baru}';

    protected $description = 'Buat satu contoh pesanan lengkap (klien, order, invoice, pembayaran, layanan aktif)';

    /** Penanda agar data demo mudah dikenali dan dibersihkan. */
    private const DEMO_EMAIL = 'demo.pelanggan@example.com';
    private const DEMO_DOMAIN = 'tokodemo.example.com';
    private const DEMO_HOSTING_DOMAIN = 'tokodemo.test';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->cleanup();
        }

        if (Client::where('email', self::DEMO_EMAIL)->exists()) {
            $this->warn('Data demo sudah ada. Jalankan dengan --fresh untuk membuat ulang.');

            return self::SUCCESS;
        }

        $this->info('Membuat contoh pesanan…');
        $this->newLine();

        DB::transaction(function () {
            // ── 1. Klien ──
            $client = Client::create([
                'name' => 'Budi Demo',
                'email' => self::DEMO_EMAIL,
                'phone' => '081200000000',
                'company' => 'Toko Demo',
                'address' => 'Jl. Contoh No. 1',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'password' => 'demo12345',
                'status' => 'active',
                'internal_notes' => 'Data contoh dari perintah lumora:demo-order.',
            ]);
            $this->line('  Klien       : ' . $client->name . ' (' . $client->email . ')');

            // ── 2. Produk hosting (dibuat kalau belum ada) ──
            $product = $this->ensureProduct();
            $this->line('  Produk      : ' . $product->name);

            // ── 3. Order hosting + order domain ──
            $hostingOrder = Order::create([
                'client_id' => $client->id,
                'product_name' => $product->name . ' (Bulanan)',
                'order_type' => 'hosting',
                'amount' => $product->price_monthly ?: 50000,
                'status' => 'active',
            ]);

            $domainOrder = Order::create([
                'client_id' => $client->id,
                'product_name' => 'Domain ' . self::DEMO_DOMAIN,
                'order_type' => 'domain',
                'amount' => 150000,
                'status' => 'active',
            ]);
            $this->line('  Order       : #' . $hostingOrder->order_number . ', #' . $domainOrder->order_number);

            // ── 4. Invoice + rinciannya ──
            $subtotal = (float) $hostingOrder->amount + (float) $domainOrder->amount;

            $invoice = Invoice::create([
                'client_id' => $client->id,
                'order_id' => $hostingOrder->id,
                'amount' => $subtotal,
                'tax' => 0,
                'discount' => 0,
                'status' => 'unpaid',
                'issue_date' => now()->subDays(2),
                'due_date' => now()->addDays(1),
            ]);

            foreach ([$hostingOrder, $domainOrder] as $order) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'order_id' => $order->id,
                    'description' => $order->product_name,
                    'amount' => $order->amount,
                ]);
            }
            $this->line('  Invoice     : ' . $invoice->invoice_number . ' — Rp ' . number_format($subtotal, 0, ',', '.'));

            // ── 5. Pembayaran (disimulasikan lunas) ──
            $gateway = PaymentGateway::where('driver', 'manual')->first()
                ?? PaymentGateway::create([
                    'name' => 'Transfer Bank (Manual)',
                    'driver' => 'manual',
                    'mode' => 'production',
                    'instructions' => "Bank Contoh\nNo. Rek: 1234567890\na/n Toko Demo",
                    'currency' => 'IDR',
                    'is_active' => true,
                ]);

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'client_id' => $client->id,
                'payment_gateway_id' => $gateway->id,
                'amount' => $subtotal,
                'fee' => 0,
                'total' => $subtotal,
                'currency' => 'IDR',
                'status' => 'pending',
                'payment_method' => 'Transfer Bank',
                'admin_note' => 'Pembayaran contoh — tidak ada uang sungguhan yang berpindah.',
            ]);

            $payment->markAsPaid('Transfer Bank');
            $this->line('  Pembayaran  : ' . $payment->reference . ' — LUNAS');

            // ── 6. Layanan aktif ──
            $hosting = HostingAccount::create([
                'client_id' => $client->id,
                'domain' => self::DEMO_HOSTING_DOMAIN,
                'package' => $product->name,
                'panel' => 'cpanel',
                'username' => 'tokodemo',
                'price' => $hostingOrder->amount,
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'next_due_date' => now()->addMonth(),
                // Sengaja "manual": tidak ada akun sungguhan yang dibuat
                // di server mana pun.
                'provision_status' => 'manual',
                'provision_message' => 'Data contoh — tidak di-provision ke server sungguhan.',
                'internal_notes' => 'Data contoh dari perintah lumora:demo-order.',
            ]);

            $hostingOrder->update(['hosting_account_id' => $hosting->id]);

            $domain = Domain::create([
                'client_id' => $client->id,
                'order_id' => $domainOrder->id,
                'tld_id' => Tld::where('extension', '.com')->value('id'),
                'domain_name' => self::DEMO_DOMAIN,
                'price' => $domainOrder->amount,
                'years' => 1,
                'status' => 'active',
                'register_date' => now(),
                'expiry_date' => now()->addYear(),
                'auto_renew' => true,
                'provision_status' => 'manual',
                'provision_message' => 'Data contoh — tidak didaftarkan ke registrar sungguhan.',
                'internal_notes' => 'Data contoh dari perintah lumora:demo-order.',
            ]);

            $this->line('  Hosting     : ' . $hosting->domain . ' (aktif)');
            $this->line('  Domain      : ' . $domain->domain_name . ' (aktif s/d ' . $domain->expiry_date->format('d M Y') . ')');
        });

        $this->newLine();
        $this->info('Selesai. Contoh pesanan sudah dibuat dari awal sampai layanan aktif.');
        $this->newLine();
        $this->line('Login sebagai klien demo untuk melihat dari sisi pelanggan:');
        $this->line('  Email    : ' . self::DEMO_EMAIL);
        $this->line('  Password : demo12345');
        $this->newLine();
        $this->comment('Nama domain memakai example.com dan .test — nama cadangan RFC 2606');
        $this->comment('yang tidak bisa didaftarkan siapa pun, jadi tidak menyentuh domain asli.');
        $this->comment('Hapus kapan saja: php artisan lumora:demo-order --fresh');

        return self::SUCCESS;
    }

    /**
     * Pastikan ada produk hosting untuk dipakai contoh.
     */
    private function ensureProduct(): Product
    {
        $existing = Product::where('is_active', true)->first();

        if ($existing) {
            return $existing;
        }

        $category = ProductCategory::firstOrCreate(
            ['slug' => 'shared-hosting'],
            ['name' => 'Shared Hosting', 'description' => 'Paket hosting untuk website pribadi dan bisnis kecil.', 'is_active' => true, 'sort_order' => 1]
        );

        return Product::create([
            'product_category_id' => $category->id,
            'name' => 'Paket Demo',
            'slug' => 'paket-demo',
            'description' => 'Paket contoh yang dibuat otomatis untuk keperluan demo.',
            'price_monthly' => 50000,
            'price_annually' => 500000,
            'is_active' => true,
            'is_featured' => true,
        ]);
    }

    /**
     * Bersihkan data demo. Dicari lewat email/domain khusus demo saja,
     * supaya data sungguhan tidak ikut terhapus.
     */
    private function cleanup(): void
    {
        $client = Client::where('email', self::DEMO_EMAIL)->first();

        if (! $client) {
            return;
        }

        // Relasi memakai cascadeOnDelete, jadi order/invoice/layanan milik
        // klien demo ikut terhapus.
        $client->delete();

        $this->warn('Data demo lama dihapus.');
    }
}
