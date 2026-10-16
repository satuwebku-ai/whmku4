<?php

use App\Http\Controllers\Site\CartController;
use App\Http\Controllers\Site\CatalogController;
use App\Http\Controllers\Site\DomainSearchController;
use App\Http\Controllers\Site\LicenseController;
use App\Http\Controllers\Site\PremiumDomainController;
use App\Http\Controllers\Site\StoreController;
use App\Http\Middleware\RedirectStoreGuestsToPublic;
use App\Http\Middleware\RequirePortalOrigin;
use App\Models\ProductType;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Store: keranjang & pemesanan (portal client)
|--------------------------------------------------------------------------
| Dimuat di domain client (member.*) dengan prefix /store oleh routes/web.php.
| Di sini pilihan pengunjung menjadi isi keranjang, lalu berlanjut ke
| checkout (routes/client.php). Tamu boleh membuat keranjang; login baru
| diminta saat checkout.
*/

// Titik serah-terima dari situs publik. Form di halaman katalog, lisensi,
// cek domain, dan domain premium (domain publik) mengirim POST langsung ke
// sini. Token CSRF milik sesi domain publik tidak berlaku di sesi client, jadi
// route ini dikecualikan dari CSRF (bootstrap/app.php, daftarnya diambil dari
// RequirePortalOrigin::HANDOFF_PATHS) dan DIGANTI pemeriksaan asal permintaan:
// hanya Origin/Referer dari domain publik atau client yang diterima. Tiap IP juga
// dibatasi 30 permintaan per menit (pembatas "cart-handoff", AppServiceProvider).
// throttle lebih dulu supaya percobaan dengan asal salah pun ikut dihitung.
Route::middleware(['throttle:cart-handoff', RequirePortalOrigin::class])->group(function () {
    Route::post('keranjang/produk', [CartController::class, 'addProduct'])->name('cart.add-product');
    Route::post('keranjang/lisensi', [CartController::class, 'addAddon'])->name('cart.add-addon');
    Route::post('keranjang/domain-premium', [CartController::class, 'addPremiumDomain'])->name('cart.add-premium-domain');
    Route::post('keranjang/domain-premium-custom', [CartController::class, 'addCustomPremium'])->name('cart.add-custom-premium');
    Route::post('keranjang/domain', [DomainSearchController::class, 'addToCart'])->name('domain.add-to-cart');
    Route::post('keranjang/transfer-domain', [DomainSearchController::class, 'submitTransfer'])->name('domains.transfer.submit');
});

// Keranjang itu sendiri (dibuka dan diubah di domain client; CSRF normal).
Route::controller(CartController::class)->prefix('keranjang')->name('cart.')->group(function () {
    Route::get('/', 'indexBootstrap')->name('index');
    Route::post('update-siklus', 'updateProductCycle')->name('update-cycle');
    Route::post('update-tahun', 'updateDomainYears')->name('update-years');
    Route::post('privacy', 'toggleWhoisPrivacy')->name('toggle-privacy');
    Route::post('hapus', 'remove')->name('remove');
    Route::post('kosongkan', 'clear')->name('clear');
});

// Halaman utama Store (/store): pintasan ke semua menu jualan dan keranjang.
// Diletakkan setelah route keranjang; path "/" di bawah prefix /store tidak bentrok.
Route::get('/', [StoreController::class, 'index'])->name('store.index');

// Halaman jualan di dalam Store (portal member), untuk klien yang login:
// /store/hosting, /store/vps, /store/domain, /store/lisensi, dst. Memakai
// controller dan view yang sama dengan etalase publik (tampil dalam layout
// toko portal member); tamu dialihkan ke etalase publik oleh middleware.
// Alamat publik TIDAK berubah (routes/store.php) -- lihat App\Support\StoreUrl.
$storeSections = ProductType::sectionPattern();

Route::middleware(RedirectStoreGuestsToPublic::class)->group(function () use ($storeSections) {
    Route::get('hosting', [CatalogController::class, 'indexBootstrap'])->name('store.hosting');
    Route::get('vps', [CatalogController::class, 'vpsBootstrap'])->name('store.vps');

    Route::get('lisensi', [LicenseController::class, 'index'])->name('store.license');
    Route::get('lisensi/{slug}', [LicenseController::class, 'show'])->name('store.license.show');

    Route::get('domain', [DomainSearchController::class, 'searchBootstrap'])->name('store.domain');
    Route::get('transfer-domain', [DomainSearchController::class, 'transferFormBootstrap'])->name('store.transfer');
    Route::get('domain-premium', [PremiumDomainController::class, 'indexBootstrap'])->name('store.domain-premium');

    // Segmen {section} = slug jenis produk (tabel product_types), sama seperti etalase publik.
    Route::get('{section}/{category}', [CatalogController::class, 'categoryBootstrap'])
        ->name('store.category')
        ->where('section', $storeSections);
    Route::get('{section}/{category}/{product}', [CatalogController::class, 'productBootstrap'])
        ->name('store.product')
        ->where('section', $storeSections);
});

// Pengecekan ketersediaan domain premium (AJAX dari halamannya; CSRF normal).
Route::post('domain-premium/cek', [PremiumDomainController::class, 'check'])->name('store.domain-premium.check');
