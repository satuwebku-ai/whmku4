<?php

use App\Http\Controllers\Site\CatalogController;
use App\Http\Controllers\Site\DomainSearchController;
use App\Http\Controllers\Site\LicenseController;
use App\Http\Controllers\Site\PremiumDomainController;
use App\Models\ProductType;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Store: menjelajah (domain publik)
|--------------------------------------------------------------------------
| Etalase yang dilihat pengunjung SEBELUM memilih: katalog hosting/VPS,
| halaman kategori & produk, lisensi, cek domain (biasa dan premium), serta
| formulir transfer domain. Semua halaman ini hanya MEMBACA. Begitu
| pengunjung memilih ("Pesan", "Tambah ke Keranjang", ...), form-nya
| dikirim ke portal client -- lihat routes/cart.php.
|
| Dimuat hanya di domain publik. URL-nya sama seperti sebelumnya
| (/hosting, /vps, /cek-domain, ...), jadi tautan lama dan SEO tidak berubah.
*/
// Segmen {section} = slug jenis produk (tabel product_types), bukan daftar tetap.
$catalogSections = ProductType::sectionPattern();

Route::controller(CatalogController::class)->group(function () use ($catalogSections) {
    Route::get('hosting', 'indexBootstrap')->name('catalog.index');
    Route::get('vps', 'vpsBootstrap')->name('catalog.vps');
    Route::get('{section}/{category}', 'categoryBootstrap')
        ->name('catalog.category')
        ->where('section', $catalogSections);
    Route::get('{section}/{category}/{product}', 'productBootstrap')
        ->name('catalog.product')
        ->where('section', $catalogSections);
});

Route::controller(LicenseController::class)->prefix('lisensi')->name('license.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{slug}', 'show')->name('show');
});

Route::controller(DomainSearchController::class)->group(function () {
    Route::get('cek-domain', 'searchBootstrap')->name('domain.search');
    Route::get('transfer-domain', 'transferFormBootstrap')->name('domains.transfer');
});

Route::controller(PremiumDomainController::class)->prefix('domain-premium')->name('domain-premium.')->group(function () {
    Route::get('/', 'indexBootstrap')->name('index');
    // Pengecekan ketersediaan (AJAX, same-origin dengan halamannya).
    Route::post('cek', 'check')->name('check');
});
