<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPricing;
use App\Models\ProductType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['slug' => 'hosting', 'name' => 'Hosting (cPanel/WHM)', 'kind' => 'hosting', 'icon' => 'fa-server', 'color' => '#4f46e5', 'description' => 'Layanan shared hosting dengan panel cPanel.', 'sort_order' => 1, 'is_active' => true],
            ['slug' => 'vps', 'name' => 'VPS / Cloud Server', 'kind' => 'vps', 'icon' => 'fa-cloud', 'color' => '#059669', 'description' => 'Server virtual cloud dengan billing deposit per jam.', 'sort_order' => 2, 'is_active' => true],
        ];
        foreach ($types as $data) {
            ProductType::updateOrCreate(['slug' => $data['slug']], $data);
        }

        $typeIds = ProductType::query()->pluck('id', 'slug');
        $categories = [
            ['slug' => 'shared-hosting', 'name' => 'Shared Hosting', 'type' => 'hosting', 'icon' => 'fa-server', 'description' => 'Paket hosting untuk website pribadi, bisnis, dan toko online.', 'sort_order' => 1],
            ['slug' => 'wordpress-hosting', 'name' => 'WordPress Hosting', 'type' => 'hosting', 'icon' => 'fa-wordpress', 'description' => 'Hosting yang dioptimalkan untuk WordPress.', 'sort_order' => 2],
            ['slug' => 'reseller-hosting', 'name' => 'Reseller Hosting', 'type' => 'hosting', 'icon' => 'fa-users', 'description' => 'Paket hosting untuk menjual kembali layanan hosting.', 'sort_order' => 3],
            ['slug' => 'cloud-vps', 'name' => 'Cloud VPS', 'type' => 'vps', 'icon' => 'fa-cloud', 'description' => 'Virtual private server dengan sumber daya terdedikasi.', 'sort_order' => 1],
        ];
        $categoryIds = [];
        foreach ($categories as $data) {
            $category = ProductGroup::updateOrCreate(
                ['slug' => $data['slug']],
                ['name' => $data['name'], 'product_type_id' => $typeIds[$data['type']], 'icon' => $data['icon'], 'description' => $data['description'], 'is_active' => true, 'sort_order' => $data['sort_order']]
            );
            $categoryIds[$data['slug']] = $category->id;
        }

        $products = [
            ['category' => 'shared-hosting', 'name' => 'Starter Hosting', 'slug' => 'starter-hosting', 'tagline' => 'Untuk website pribadi dan portofolio', 'description' => 'Paket awal untuk website ringan dengan panel cPanel.', 'features' => ['1 website', '5 GB NVMe storage', 'SSL gratis', 'Email hosting', 'Backup berkala'], 'monthly' => 25000, 'quarterly' => 70000, 'semi' => 135000, 'annual' => 250000, 'setup' => 0, 'domain' => 'optional', 'mode' => 'invoice', 'featured' => false, 'sort' => 1, 'package' => 'starter'],
            ['category' => 'shared-hosting', 'name' => 'Business Hosting', 'slug' => 'business-hosting', 'tagline' => 'Untuk bisnis dan website berkembang', 'description' => 'Sumber daya lebih besar untuk website bisnis.', 'features' => ['5 website', '20 GB NVMe storage', 'SSL gratis', 'Email hosting', 'Backup harian'], 'monthly' => 55000, 'quarterly' => 155000, 'semi' => 295000, 'annual' => 550000, 'setup' => 0, 'domain' => 'optional', 'mode' => 'invoice', 'featured' => true, 'sort' => 2, 'package' => 'business'],
            ['category' => 'shared-hosting', 'name' => 'Pro Hosting', 'slug' => 'pro-hosting', 'tagline' => 'Performa untuk website dengan trafik tinggi', 'description' => 'Paket hosting dengan kapasitas dan resource lebih tinggi.', 'features' => ['Unlimited website sesuai kebijakan resource', '50 GB NVMe storage', 'SSL gratis', 'Backup harian', 'Prioritas support'], 'monthly' => 99000, 'quarterly' => 280000, 'semi' => 535000, 'annual' => 990000, 'setup' => 0, 'domain' => 'optional', 'mode' => 'invoice', 'featured' => false, 'sort' => 3, 'package' => 'pro'],
            ['category' => 'wordpress-hosting', 'name' => 'WordPress Basic', 'slug' => 'wordpress-basic', 'tagline' => 'WordPress lebih mudah dikelola', 'description' => 'Paket untuk blog dan website WordPress.', 'features' => ['1 website WordPress', '10 GB NVMe storage', 'SSL gratis', 'Optimasi cache'], 'monthly' => 35000, 'quarterly' => 99000, 'semi' => 189000, 'annual' => 350000, 'setup' => 0, 'domain' => 'optional', 'mode' => 'invoice', 'featured' => false, 'sort' => 1, 'package' => 'wp-basic'],
            ['category' => 'reseller-hosting', 'name' => 'Reseller Starter', 'slug' => 'reseller-starter', 'tagline' => 'Mulai bisnis hosting sendiri', 'description' => 'Paket reseller untuk mengelola beberapa akun hosting.', 'features' => ['15 akun cPanel', '30 GB NVMe storage', 'WHM reseller', 'SSL gratis'], 'monthly' => 150000, 'quarterly' => 425000, 'semi' => 810000, 'annual' => 1500000, 'setup' => 0, 'domain' => 'none', 'mode' => 'invoice', 'featured' => false, 'sort' => 1, 'package' => 'reseller-starter'],
            ['category' => 'cloud-vps', 'name' => 'Cloud VPS S', 'slug' => 'cloud-vps-s', 'tagline' => 'VPS untuk aplikasi kecil', 'description' => 'VPS cloud untuk staging, bot, dan aplikasi kecil. Harga penggunaan dihitung per jam.', 'features' => ['2 vCPU', '4 GB RAM', '60 GB SSD', 'Akses root', 'Pilihan Linux'], 'monthly' => null, 'quarterly' => null, 'semi' => null, 'annual' => null, 'setup' => 0, 'domain' => 'none', 'mode' => 'deposit', 'featured' => true, 'sort' => 1, 'package' => null, 'vcpu' => 250, 'ram' => 80, 'storage' => 2],
            ['category' => 'cloud-vps', 'name' => 'Cloud VPS M', 'slug' => 'cloud-vps-m', 'tagline' => 'Resource seimbang untuk bisnis', 'description' => 'VPS cloud untuk aplikasi produksi skala menengah. Harga penggunaan dihitung per jam.', 'features' => ['4 vCPU', '8 GB RAM', '120 GB SSD', 'Akses root', 'Pilihan Linux'], 'monthly' => null, 'quarterly' => null, 'semi' => null, 'annual' => null, 'setup' => 0, 'domain' => 'none', 'mode' => 'deposit', 'featured' => false, 'sort' => 2, 'package' => null, 'vcpu' => 250, 'ram' => 80, 'storage' => 2],
            ['category' => 'cloud-vps', 'name' => 'Cloud VPS L', 'slug' => 'cloud-vps-l', 'tagline' => 'Untuk workload lebih berat', 'description' => 'VPS cloud dengan resource lebih besar. Harga penggunaan dihitung per jam.', 'features' => ['8 vCPU', '16 GB RAM', '240 GB SSD', 'Akses root', 'Pilihan Linux'], 'monthly' => null, 'quarterly' => null, 'semi' => null, 'annual' => null, 'setup' => 0, 'domain' => 'none', 'mode' => 'deposit', 'featured' => false, 'sort' => 3, 'package' => null, 'vcpu' => 250, 'ram' => 80, 'storage' => 2],
            ['category' => 'shared-hosting', 'name' => 'Micro Hosting', 'slug' => 'micro-hosting', 'tagline' => 'Landing page dan website sederhana', 'description' => 'Paket hemat untuk satu website kecil.', 'features' => ['1 website', '2 GB NVMe storage', 'SSL gratis', '1 database', 'Email hosting'], 'monthly' => 15000, 'quarterly' => 42000, 'semi' => 80000, 'annual' => 150000, 'setup' => 0, 'domain' => 'optional', 'mode' => 'invoice', 'featured' => false, 'sort' => 0, 'package' => 'micro'],
            ['category' => 'shared-hosting', 'name' => 'Premium Hosting', 'slug' => 'premium-hosting', 'tagline' => 'Resource lebih besar untuk website aktif', 'description' => 'Paket untuk beberapa website bisnis dengan kebutuhan resource lebih tinggi.', 'features' => ['10 website', '40 GB NVMe storage', 'SSL gratis', 'Backup harian', 'Staging website'], 'monthly' => 79000, 'quarterly' => 225000, 'semi' => 430000, 'annual' => 790000, 'setup' => 0, 'domain' => 'optional', 'mode' => 'invoice', 'featured' => true, 'sort' => 4, 'package' => 'premium'],
            ['category' => 'shared-hosting', 'name' => 'Ultimate Hosting', 'slug' => 'ultimate-hosting', 'tagline' => 'Untuk website bisnis dengan trafik tinggi', 'description' => 'Paket kapasitas besar dengan dukungan prioritas.', 'features' => ['25 website', '100 GB NVMe storage', 'SSL gratis', 'Backup harian', 'Prioritas support'], 'monthly' => 149000, 'quarterly' => 425000, 'semi' => 805000, 'annual' => 1490000, 'setup' => 0, 'domain' => 'optional', 'mode' => 'invoice', 'featured' => false, 'sort' => 5, 'package' => 'ultimate'],
            ['category' => 'wordpress-hosting', 'name' => 'WordPress Starter', 'slug' => 'wordpress-starter', 'tagline' => 'Blog dan website WordPress pemula', 'description' => 'Hosting WordPress untuk memulai website dengan mudah.', 'features' => ['1 website WordPress', '5 GB NVMe storage', 'SSL gratis', 'Cache WordPress', 'Backup mingguan'], 'monthly' => 25000, 'quarterly' => 70000, 'semi' => 135000, 'annual' => 250000, 'setup' => 0, 'domain' => 'optional', 'mode' => 'invoice', 'featured' => false, 'sort' => 0, 'package' => 'wp-starter'],
            ['category' => 'wordpress-hosting', 'name' => 'WordPress Business', 'slug' => 'wordpress-business', 'tagline' => 'WordPress untuk bisnis dan toko online', 'description' => 'Resource dan fitur keamanan untuk website bisnis berbasis WordPress.', 'features' => ['5 website WordPress', '25 GB NVMe storage', 'SSL gratis', 'Object cache', 'Backup harian', 'Staging'], 'monthly' => 65000, 'quarterly' => 185000, 'semi' => 350000, 'annual' => 650000, 'setup' => 0, 'domain' => 'optional', 'mode' => 'invoice', 'featured' => true, 'sort' => 2, 'package' => 'wp-business'],
            ['category' => 'wordpress-hosting', 'name' => 'WordPress Pro', 'slug' => 'wordpress-pro', 'tagline' => 'Performa WordPress untuk trafik tinggi', 'description' => 'Paket WordPress dengan resource lebih tinggi dan dukungan prioritas.', 'features' => ['10 website WordPress', '60 GB NVMe storage', 'SSL gratis', 'Backup harian', 'Staging', 'Prioritas support'], 'monthly' => 119000, 'quarterly' => 339000, 'semi' => 645000, 'annual' => 1190000, 'setup' => 0, 'domain' => 'optional', 'mode' => 'invoice', 'featured' => false, 'sort' => 3, 'package' => 'wp-pro'],
            ['category' => 'reseller-hosting', 'name' => 'Reseller Basic', 'slug' => 'reseller-basic', 'tagline' => 'Mulai menjual hosting ke pelanggan', 'description' => 'Paket reseller untuk bisnis hosting skala awal.', 'features' => ['5 akun cPanel', '20 GB NVMe storage', 'WHM reseller', 'SSL gratis', 'Branding nameserver'], 'monthly' => 99000, 'quarterly' => 280000, 'semi' => 535000, 'annual' => 990000, 'setup' => 0, 'domain' => 'none', 'mode' => 'invoice', 'featured' => false, 'sort' => 0, 'package' => 'reseller-basic'],
            ['category' => 'reseller-hosting', 'name' => 'Reseller Business', 'slug' => 'reseller-business', 'tagline' => 'Untuk penyedia hosting berkembang', 'description' => 'Lebih banyak akun dan kapasitas untuk usaha reseller.', 'features' => ['30 akun cPanel', '60 GB NVMe storage', 'WHM reseller', 'SSL gratis', 'Branding nameserver', 'Backup harian'], 'monthly' => 249000, 'quarterly' => 710000, 'semi' => 1345000, 'annual' => 2490000, 'setup' => 0, 'domain' => 'none', 'mode' => 'invoice', 'featured' => true, 'sort' => 2, 'package' => 'reseller-business'],
            ['category' => 'reseller-hosting', 'name' => 'Reseller Pro', 'slug' => 'reseller-pro', 'tagline' => 'Kapasitas besar untuk bisnis hosting', 'description' => 'Paket reseller skala besar dengan resource yang lebih luas.', 'features' => ['75 akun cPanel', '150 GB NVMe storage', 'WHM reseller', 'SSL gratis', 'Branding nameserver', 'Backup harian', 'Prioritas support'], 'monthly' => 449000, 'quarterly' => 1280000, 'semi' => 2425000, 'annual' => 4490000, 'setup' => 0, 'domain' => 'none', 'mode' => 'invoice', 'featured' => false, 'sort' => 3, 'package' => 'reseller-pro'],
            ['category' => 'cloud-vps', 'name' => 'Cloud VPS XS', 'slug' => 'cloud-vps-xs', 'tagline' => 'VPS ringan untuk development', 'description' => 'VPS untuk eksperimen, development, dan aplikasi ringan dengan billing per jam.', 'features' => ['1 vCPU', '2 GB RAM', '30 GB SSD', 'Akses root', 'Pilihan image Linux'], 'monthly' => null, 'quarterly' => null, 'semi' => null, 'annual' => null, 'setup' => 0, 'domain' => 'none', 'mode' => 'deposit', 'featured' => false, 'sort' => 0, 'package' => null, 'vcpu' => 180, 'ram' => 60, 'storage' => 1],
            ['category' => 'cloud-vps', 'name' => 'Cloud VPS XL', 'slug' => 'cloud-vps-xl', 'tagline' => 'VPS untuk workload intensif', 'description' => 'VPS dengan resource besar untuk aplikasi dan layanan bisnis.', 'features' => ['16 vCPU', '32 GB RAM', '480 GB SSD', 'Akses root', 'Pilihan image Linux', 'Backup opsional'], 'monthly' => null, 'quarterly' => null, 'semi' => null, 'annual' => null, 'setup' => 0, 'domain' => 'none', 'mode' => 'deposit', 'featured' => false, 'sort' => 4, 'package' => null, 'vcpu' => 220, 'ram' => 70, 'storage' => 2],
            ['category' => 'cloud-vps', 'name' => 'Cloud VPS 2XL', 'slug' => 'cloud-vps-2xl', 'tagline' => 'Resource ekstra untuk sistem besar', 'description' => 'VPS kapasitas ekstra untuk aplikasi yang membutuhkan resource tinggi.', 'features' => ['32 vCPU', '64 GB RAM', '960 GB SSD', 'Akses root', 'Pilihan image Linux', 'Backup opsional'], 'monthly' => null, 'quarterly' => null, 'semi' => null, 'annual' => null, 'setup' => 0, 'domain' => 'none', 'mode' => 'deposit', 'featured' => false, 'sort' => 5, 'package' => null, 'vcpu' => 200, 'ram' => 65, 'storage' => 2],
            ['category' => 'cloud-vps', 'name' => 'Cloud VPS Windows S', 'slug' => 'cloud-vps-windows-s', 'tagline' => 'VPS Windows untuk aplikasi kompatibel', 'description' => 'Spesifikasi VPS kecil untuk workload Windows; biaya lisensi perlu dikonfigurasi terpisah.', 'features' => ['2 vCPU', '4 GB RAM', '80 GB SSD', 'Windows Server image jika tersedia', 'Akses administrator'], 'monthly' => null, 'quarterly' => null, 'semi' => null, 'annual' => null, 'setup' => 0, 'domain' => 'none', 'mode' => 'deposit', 'featured' => false, 'sort' => 6, 'package' => null, 'vcpu' => 300, 'ram' => 80, 'storage' => 2],
            ['category' => 'cloud-vps', 'name' => 'Cloud VPS Storage', 'slug' => 'cloud-vps-storage', 'tagline' => 'VPS untuk aplikasi yang butuh storage', 'description' => 'Profil produk VPS yang menekankan kapasitas penyimpanan.', 'features' => ['4 vCPU', '8 GB RAM', '500 GB SSD', 'Akses root', 'Pilihan image Linux'], 'monthly' => null, 'quarterly' => null, 'semi' => null, 'annual' => null, 'setup' => 0, 'domain' => 'none', 'mode' => 'deposit', 'featured' => false, 'sort' => 7, 'package' => null, 'vcpu' => 220, 'ram' => 70, 'storage' => 1],
            ['category' => 'cloud-vps', 'name' => 'Cloud VPS Compute', 'slug' => 'cloud-vps-compute', 'tagline' => 'VPS untuk aplikasi komputasi', 'description' => 'Profil VPS yang mengutamakan CPU untuk aplikasi komputasi.', 'features' => ['8 vCPU', '8 GB RAM', '100 GB SSD', 'Akses root', 'Pilihan image Linux'], 'monthly' => null, 'quarterly' => null, 'semi' => null, 'annual' => null, 'setup' => 0, 'domain' => 'none', 'mode' => 'deposit', 'featured' => false, 'sort' => 8, 'package' => null, 'vcpu' => 280, 'ram' => 55, 'storage' => 2],
            ['category' => 'cloud-vps', 'name' => 'Cloud VPS Memory', 'slug' => 'cloud-vps-memory', 'tagline' => 'VPS untuk aplikasi intensif memori', 'description' => 'Profil VPS yang mengutamakan kapasitas RAM.', 'features' => ['4 vCPU', '16 GB RAM', '120 GB SSD', 'Akses root', 'Pilihan image Linux'], 'monthly' => null, 'quarterly' => null, 'semi' => null, 'annual' => null, 'setup' => 0, 'domain' => 'none', 'mode' => 'deposit', 'featured' => false, 'sort' => 9, 'package' => null, 'vcpu' => 200, 'ram' => 95, 'storage' => 2],
        ];

        DB::transaction(function () use ($products, $categoryIds) {
            foreach ($products as $item) {
                $attributes = [
                    'product_category_id' => $categoryIds[$item['category']],
                    'name' => $item['name'], 'tagline' => $item['tagline'], 'description' => $item['description'],
                    'features' => $item['features'], 'price_monthly' => $item['monthly'],
                    'price_quarterly' => $item['quarterly'], 'price_semi_annually' => $item['semi'],
                    'price_annually' => $item['annual'], 'setup_fee' => $item['setup'],
                    'domain_option' => $item['domain'], 'billing_mode' => $item['mode'],
                    'is_active' => true, 'is_featured' => $item['featured'], 'stock' => null,
                    'reserved_stock' => 0, 'sort_order' => $item['sort'], 'panel_package' => $item['package'],
                ];
                if (isset($item['vcpu'])) {
                    $attributes += [
                        'pricing_mode' => 'manual', 'price_per_vcpu_hour' => $item['vcpu'],
                        'price_per_ram_gb_hour' => $item['ram'],
                        'price_per_storage_gb_hour' => $item['storage'],
                    ];
                }
                $product = Product::updateOrCreate(['slug' => $item['slug']], $attributes);

                foreach (['monthly' => $item['monthly'], 'quarterly' => $item['quarterly'], 'semi_annually' => $item['semi'], 'annually' => $item['annual']] as $cycle => $price) {
                    if ($price !== null) {
                        ProductPricing::updateOrCreate(
                            ['product_id' => $product->id, 'client_group_id' => null, 'billing_cycle' => $cycle],
                            ['price' => $price, 'setup_fee' => $item['setup']]
                        );
                    }
                }
            }
        });
    }
}
