<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Page;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Models\Tld;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Data contoh supaya dashboard & halaman CRUD langsung terisi.
     * Aman dijalankan berulang kali (pakai firstOrCreate berbasis email).
     */
    public function run(): void
    {
        $clientsData = [
            ['name' => 'Budi Santoso', 'email' => 'budi.santoso@example.com', 'company' => null, 'phone' => '0812-1111-2222'],
            ['name' => 'Siti Aminah', 'email' => 'siti.aminah@example.com', 'company' => null, 'phone' => '0813-2222-3333'],
            ['name' => 'PT Maju Jaya', 'email' => 'admin@majujaya.co.id', 'company' => 'PT Maju Jaya', 'phone' => '021-555-1234'],
            ['name' => 'Andi Wijaya', 'email' => 'andi.wijaya@example.com', 'company' => null, 'phone' => '0815-4444-5555'],
            ['name' => 'Dewi Lestari', 'email' => 'dewi.lestari@example.com', 'company' => null, 'phone' => '0816-6666-7777'],
        ];

        $clients = collect($clientsData)->map(function ($data) {
            return Client::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'company' => $data['company'],
                    'phone' => $data['phone'],
                    'city' => 'Jakarta',
                    'country' => 'Indonesia',
                    'status' => 'active',
                    // Password demo untuk mencoba client area: "password"
                    'password' => 'password',
                ]
            );
        });

        $orderSeed = [
            ['client' => 0, 'product' => 'Cloud Hosting - Pro', 'type' => 'hosting', 'amount' => 350000, 'status' => 'pending'],
            ['client' => 1, 'product' => 'Domain .com', 'type' => 'domain', 'amount' => 150000, 'status' => 'active'],
            ['client' => 2, 'product' => 'VPS - Business', 'type' => 'vps', 'amount' => 1200000, 'status' => 'active'],
            ['client' => 3, 'product' => 'Shared Hosting - Starter', 'type' => 'hosting', 'amount' => 75000, 'status' => 'suspended'],
            ['client' => 4, 'product' => 'Domain .id', 'type' => 'domain', 'amount' => 225000, 'status' => 'pending'],
        ];

        foreach ($orderSeed as $row) {
            $client = $clients[$row['client']];

            $hostingAccount = null;
            if ($row['type'] === 'hosting') {
                $hostingAccount = HostingAccount::firstOrCreate(
                    ['client_id' => $client->id, 'domain' => strtolower(str_replace(' ', '', $client->name)) . '.com'],
                    [
                        'package' => $row['product'],
                        'panel' => 'cpanel',
                        'price' => $row['amount'],
                        'billing_cycle' => 'monthly',
                        'status' => $row['status'] === 'active' ? 'active' : ($row['status'] === 'suspended' ? 'suspended' : 'pending'),
                        'next_due_date' => now()->addDays(30),
                    ]
                );
            }

            $order = Order::firstOrCreate(
                ['client_id' => $client->id, 'product_name' => $row['product']],
                [
                    'hosting_account_id' => $hostingAccount?->id,
                    'order_type' => $row['type'],
                    'amount' => $row['amount'],
                    'status' => $row['status'],
                ]
            );

            if ($row['status'] === 'active') {
                Invoice::firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'client_id' => $client->id,
                        'amount' => $row['amount'],
                        'tax' => 0,
                        'total' => $row['amount'],
                        'status' => 'paid',
                        'issue_date' => now()->subDays(5),
                        'due_date' => now()->addDays(2),
                        'paid_at' => now()->subDays(2),
                        'payment_method' => 'Transfer Bank',
                    ]
                );
            }
        }

        // TLD contoh (harga jual) — belum terhubung ke registrar sungguhan,
        // hubungkan lewat menu Registrar setelah kredensial Namecheap siap.
        $tldSeed = [
            ['extension' => '.com', 'register' => 150000, 'renew' => 165000, 'transfer' => 150000],
            ['extension' => '.net', 'register' => 175000, 'renew' => 190000, 'transfer' => 175000],
            ['extension' => '.id', 'register' => 225000, 'renew' => 225000, 'transfer' => 225000],
            ['extension' => '.co.id', 'register' => 125000, 'renew' => 125000, 'transfer' => 125000],
        ];

        foreach ($tldSeed as $row) {
            Tld::firstOrCreate(
                ['extension' => $row['extension']],
                [
                    'register_price' => $row['register'],
                    'renew_price' => $row['renew'],
                    'transfer_price' => $row['transfer'],
                    'min_years' => 1,
                    'max_years' => 10,
                    'is_active' => true,
                ]
            );
        }

        // Gateway transfer manual sebagai contoh — langsung bisa dipakai
        // tanpa kredensial API apapun.
        PaymentGateway::firstOrCreate(
            ['driver' => 'manual', 'name' => 'Transfer Bank (Manual)'],
            [
                'mode' => 'production',
                'instructions' => "Bank BCA\nNo. Rekening: 1234567890\na/n PT Contoh Hosting\n\nSetelah transfer, kirim bukti ke support@contohhosting.com",
                'fee_flat' => 0,
                'fee_percent' => 0,
                'currency' => 'IDR',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // Halaman statis dasar yang hampir selalu dibutuhkan.
        $pageSeed = [
            ['title' => 'Tentang Kami', 'slug' => 'tentang-kami', 'content' => '<h2>Tentang Kami</h2><p>Silakan ubah isi halaman ini lewat menu Konten &amp; Halaman.</p>'],
            ['title' => 'Syarat & Ketentuan', 'slug' => 'syarat-ketentuan', 'content' => '<h2>Syarat &amp; Ketentuan</h2><p>Tuliskan syarat layanan hosting Anda di sini.</p>'],
            ['title' => 'Kebijakan Privasi', 'slug' => 'kebijakan-privasi', 'content' => '<h2>Kebijakan Privasi</h2><p>Jelaskan bagaimana data pelanggan dikelola.</p>'],
        ];

        foreach ($pageSeed as $i => $row) {
            Page::firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'meta_description' => 'Halaman ' . $row['title'] . ' — silakan lengkapi deskripsi SEO-nya.',
                    'is_published' => true,
                    'show_in_footer' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }

        // Nilai awal pengaturan situs.
        Setting::putMany([
            'site_name' => config('app.name', 'Lumora Hosting'),
            'site_tagline' => 'Hosting & Domain Terpercaya',
        ], 'general');

        Setting::putMany(['livechat_provider' => 'none'], 'livechat');

        // Katalog produk contoh (Fase 7b) — dua kategori dengan beberapa paket.
        $sharedCategory = ProductCategory::firstOrCreate(
            ['slug' => 'shared-hosting'],
            ['name' => 'Shared Hosting', 'description' => 'Cocok untuk website pribadi, blog, dan portofolio.', 'icon' => 'fa-server', 'is_active' => true, 'sort_order' => 1]
        );

        $vpsCategory = ProductCategory::firstOrCreate(
            ['slug' => 'vps'],
            ['name' => 'VPS', 'description' => 'Sumber daya khusus untuk website dengan trafik tinggi.', 'icon' => 'fa-microchip', 'is_active' => true, 'sort_order' => 2]
        );

        $productSeed = [
            [
                'category' => $sharedCategory, 'name' => 'Hosting Starter', 'featured' => false,
                'tagline' => 'Awal yang pas untuk website pertama Anda',
                'features' => ['5 GB SSD Storage', '50 GB Bandwidth', '1 Website', 'Free SSL', 'Support 24/7'],
                'monthly' => 25000, 'annually' => 250000, 'domain_option' => 'optional',
            ],
            [
                'category' => $sharedCategory, 'name' => 'Hosting Pro', 'featured' => true,
                'tagline' => 'Paling laris — cukup untuk toko online kecil',
                'features' => ['20 GB SSD Storage', 'Unlimited Bandwidth', '5 Website', 'Free SSL', 'Free Domain .com', 'Support 24/7 Prioritas'],
                'monthly' => 55000, 'annually' => 550000, 'domain_option' => 'optional',
            ],
            [
                'category' => $sharedCategory, 'name' => 'Hosting Business', 'featured' => false,
                'tagline' => 'Untuk website dengan trafik menengah',
                'features' => ['50 GB SSD Storage', 'Unlimited Bandwidth', 'Unlimited Website', 'Free SSL', 'Free Domain', 'Backup Harian'],
                'monthly' => 95000, 'annually' => 950000, 'domain_option' => 'optional',
            ],
            [
                'category' => $vpsCategory, 'name' => 'VPS Basic', 'featured' => false,
                'tagline' => '2 vCPU, 4 GB RAM — kontrol penuh via root access',
                'features' => ['2 vCPU', '4 GB RAM', '80 GB SSD', 'Full Root Access', 'Bandwidth 2 TB'],
                'monthly' => 150000, 'annually' => 1500000, 'domain_option' => 'none',
            ],
        ];

        foreach ($productSeed as $row) {
            Product::firstOrCreate(
                ['product_category_id' => $row['category']->id, 'name' => $row['name']],
                [
                    'tagline' => $row['tagline'],
                    'features' => $row['features'],
                    'price_monthly' => $row['monthly'],
                    'price_annually' => $row['annually'],
                    'domain_option' => $row['domain_option'],
                    'is_active' => true,
                    'is_featured' => $row['featured'],
                ]
            );
        }
    }
}
