<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tld;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /**
     * Halaman depan situs.
     */
    public function home(): View
    {
        return view('public.home', $this->homeData());
    }

    public function homeBootstrap(): View
    {
        return view('public.home', $this->homeData());
    }

    private function homeData(): array
    {
        // Diatur lewat Admin -> Sistem -> Pengaturan -> Halaman Depan.
        $categoriesLimit = (int) \App\Models\Setting::get('home_categories_limit', 6);
        $featuredLimit = max(1, (int) \App\Models\Setting::get('home_featured_limit', 3));
        $announcementsLimit = max(1, (int) \App\Models\Setting::get('home_announcements_limit', 3));
        $vpsLimit = max(1, (int) \App\Models\Setting::get('home_vps_limit', 3));

        $categories = ProductCategory::active()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($cat) => $cat->products_count > 0);

        // 0 berarti "tanpa batas".
        if ($categoriesLimit > 0) {
            $categories = $categories->take($categoriesLimit);
        }

        // Produk VPS dipisah dari hosting biasa -- dibedakan lewat server
        // yang dipakai (panel 'idcloudhost' = cloud/VPS), pola yang sama
        // dengan ProductController.
        $cloudServerIds = \App\Models\Server::whereIn('panel', ['idcloudhost'])->pluck('id');

        $featured = Product::active()
            ->with('category')
            ->where('is_featured', true)
            ->where(fn ($q) => $q->whereNotIn('server_id', $cloudServerIds)->orWhereNull('server_id'))
            ->orderBy('sort_order')
            ->take($featuredLimit)
            ->get();

        // Kalau belum ada yang ditandai unggulan, tampilkan paket termurah
        // supaya halaman depan tidak kosong.
        if ($featured->isEmpty()) {
            $featured = Product::active()
                ->with('category')
                ->where(fn ($q) => $q->whereNotIn('server_id', $cloudServerIds)->orWhereNull('server_id'))
                ->orderByRaw('COALESCE(price_monthly, price_quarterly, price_semi_annually, price_annually) ASC')
                ->take($featuredLimit)
                ->get();
        }

        // Paket VPS -- section terpisah dari hosting biasa.
        $vpsProducts = $cloudServerIds->isNotEmpty()
            ? Product::active()
                ->with('category')
                ->whereIn('server_id', $cloudServerIds)
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->take($vpsLimit)
                ->get()
            : collect();

        // TLD populer untuk ditampilkan di bawah kotak pencarian domain.
        $popularTlds = Tld::where('is_active', true)
            ->where('register_price', '>', 0)
            ->orderBy('register_price')
            ->take(6)
            ->get();

        $announcements = Announcement::live()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->take($announcementsLimit)
            ->get();

        $banners = \App\Models\PromoBanner::live()->forPage('home')->orderBy('sort_order')->get();

        // ── Susunan section beranda ──
        // Urutan bawaan sesuai alur yang diinginkan: cari domain dulu,
        // lalu banner promo, baru paket-paketnya, dan ditutup ajakan
        // mendaftar. Admin bisa mengubah urutan & menyembunyikan section
        // lewat Pengaturan Halaman Depan.
        //
        // 'available' = section ini punya isi. Section tanpa isi
        // OTOMATIS tidak dirender, walau toggle-nya menyala -- supaya
        // tidak ada blok kosong menganga di beranda (mis. section VPS
        // saat belum ada satu pun paket VPS dibuat).
        $sectionData = [
            'domain'        => true,
            'banner'        => $banners->isNotEmpty(),
            'benefits'      => true,
            'hosting'       => $featured->isNotEmpty(),
            'vps'           => $vpsProducts->isNotEmpty(),
            'categories'    => $categories->isNotEmpty(),
            'announcements' => $announcements->isNotEmpty(),
            'cta'           => true,
        ];

        $defaultOrder = ['domain', 'banner', 'benefits', 'hosting', 'vps', 'categories', 'announcements', 'cta'];

        $savedOrder = json_decode((string) \App\Models\Setting::get('home_section_order'), true);
        $order = is_array($savedOrder) && $savedOrder ? $savedOrder : $defaultOrder;

        // Section baru yang belum ada di urutan tersimpan tetap ikut
        // tampil (di belakang), bukan hilang diam-diam.
        $order = array_values(array_unique(array_merge(
            array_values(array_intersect($order, $defaultOrder)),
            $defaultOrder
        )));

        $homeSections = [];

        foreach ($order as $key) {
            $visible = \App\Models\Setting::get('home_show_' . $key, '1') === '1';
            $homeSections[$key] = $visible && ($sectionData[$key] ?? false);
        }

        $homeOrder = $order;

        return compact(
            'categories', 'featured', 'vpsProducts', 'popularTlds',
            'announcements', 'banners', 'homeSections', 'homeOrder'
        );
    }

    public function index(): View
    {
        return view('public.catalog.index', $this->indexData());
    }

    public function indexBootstrap(): View
    {
        return view('public.catalog.index', $this->indexData());
    }

    private function indexData(): array
    {
        $categories = ProductCategory::active()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($cat) => $cat->products_count > 0);

        $featured = Product::active()
            ->with('category')
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->take(6)
            ->get();

        $banners = \App\Models\PromoBanner::live()->forPage('catalog')->orderBy('sort_order')->get();

        return compact('categories', 'featured', 'banners');
    }

    public function category(string $section, string $slug): View
    {
        return view('public.catalog.category', $this->categoryData($slug));
    }

    public function categoryBootstrap(string $section, string $slug): View
    {
        return view('public.catalog.category', $this->categoryData($slug));
    }

    private function categoryData(string $slug): array
    {
        $category = ProductCategory::active()->where('slug', $slug)->firstOrFail();

        $products = $category->products()
            ->active()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12);

        return compact('category', 'products');
    }

    public function product(string $section, string $categorySlug, string $productSlug): View
    {
        return view('public.catalog.product', $this->productData($categorySlug, $productSlug));
    }

    public function productBootstrap(string $section, string $categorySlug, string $productSlug): View
    {
        return view('public.catalog.product', $this->productData($categorySlug, $productSlug));
    }

    private function productData(string $categorySlug, string $productSlug): array
    {
        $category = ProductCategory::active()->where('slug', $categorySlug)->firstOrFail();

        $product = Product::active()
            ->where('product_category_id', $category->id)
            ->where('slug', $productSlug)
            ->with(['optionGroups' => fn ($q) => $q->active()->with(['options' => fn ($q2) => $q2->active()])])
            ->firstOrFail();

        $related = Product::active()
            ->where('product_category_id', $category->id)
            ->where('id', '!=', $product->id)
            ->take(3)
            ->get();

        return compact('category', 'product', 'related');
    }
}
