<?php

use App\Http\Controllers\Payment\WebhookController;
use App\Http\Controllers\Site\CartController;
use App\Http\Controllers\Site\CatalogController;
use App\Http\Controllers\Site\DomainSearchController;
use App\Http\Controllers\Site\ChatController as SiteChatController;
use App\Http\Controllers\Site\PageController as SitePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'homeBootstrap'])->name('home');

Route::prefix('admin')->name('admin.')->group(base_path('routes/admin.php'));

Route::prefix('client')->name('client.')->group(base_path('routes/client.php'));

// Logo, favicon, gambar banner — dilayani lewat Laravel (bukan file
// statis), supaya kebal terhadap perbedaan folder repository vs folder
// yang benar-benar dilayani web server (lihat BrandingAssetController).
Route::get('branding/{filename}', [\App\Http\Controllers\BrandingAssetController::class, 'branding'])->name('branding.file');
Route::get('banner-image/{filename}', [\App\Http\Controllers\BrandingAssetController::class, 'banner'])->name('banner.file');

// Font Awesome lokal — bukan CDN, supaya ikon tidak hilang/tampilan
// tidak kosong kalau CDN pihak ketiga lambat/tidak bisa diakses. Nama
// rute meniru struktur folder asli (css/, webfonts/) supaya path
// relatif di dalam CSS-nya tetap benar tanpa perlu disunting.
Route::get('vendor/fontawesome/css/{filename}', [\App\Http\Controllers\BrandingAssetController::class, 'fontAwesomeCss'])->name('fontawesome.css');
Route::get('vendor/fontawesome/webfonts/{filename}', [\App\Http\Controllers\BrandingAssetController::class, 'fontAwesomeWebfont'])->name('fontawesome.webfont');
Route::get('vendor/tailwind/browser.js', [\App\Http\Controllers\BrandingAssetController::class, 'tailwindBrowser'])->name('tailwind.browser');

/*
|--------------------------------------------------------------------------
| Toko Publik: Katalog, Cek Domain, Keranjang (Fase 7b)
|--------------------------------------------------------------------------
| Semua route ini bisa diakses TANPA login — pengunjung boleh menjelajah
| katalog, cek domain, dan mengisi keranjang sebelum daftar/login.
| Keranjang disimpan di session (lihat App\Services\Cart\CartService),
| checkout sungguhan (jadi Order + Invoice) menyusul di Fase 7c.
*/
Route::controller(CatalogController::class)->group(function () {
    Route::get('hosting', 'indexBootstrap')->name('catalog.index');
    // Prefix URL mengikuti jenis kategori: /hosting/... untuk hosting
    // biasa, /vps/... untuk cloud server. Dibatasi where() supaya tidak
    // menangkap path lain yang tidak dimaksud.
    Route::get('{section}/{category}', 'categoryBootstrap')->name('catalog.category')->where('section', 'hosting|vps');
    Route::get('{section}/{category}/{product}', 'productBootstrap')->name('catalog.product')->where('section', 'hosting|vps');
});

Route::controller(DomainSearchController::class)->group(function () {
    Route::get('cek-domain', 'searchBootstrap')->name('domain.search');
    Route::post('cek-domain/keranjang', 'addToCart')->name('domain.add-to-cart');
    Route::get('transfer-domain', 'transferFormBootstrap')->name('domains.transfer');
    Route::post('transfer-domain', 'submitTransfer')->name('domains.transfer.submit');
});

Route::controller(CartController::class)->prefix('keranjang')->name('cart.')->group(function () {
    Route::get('/', 'indexBootstrap')->name('index');
    Route::post('produk', 'addProduct')->name('add-product');
    Route::post('update-siklus', 'updateProductCycle')->name('update-cycle');
    Route::post('update-tahun', 'updateDomainYears')->name('update-years');
    Route::post('privacy', 'toggleWhoisPrivacy')->name('toggle-privacy');
    Route::post('hapus', 'remove')->name('remove');
    Route::post('kosongkan', 'clear')->name('clear');
});

/*
|--------------------------------------------------------------------------
| Halaman Publik (CMS)
|--------------------------------------------------------------------------
| Halaman statis dikelola lewat menu Konten & Halaman. URL-nya bersih di
| root (mis. /contact), bukan diawali /p/ — lihat route catch-all di
| PALING BAWAH file ini untuk alasan urutan pendaftarannya.
|
| Slug yang bisa bentrok dengan route sistem (mis. "admin", "hosting")
| ditolak sejak dibuat — lihat Page::RESERVED_SLUGS.
*/
/*
|--------------------------------------------------------------------------
| Widget Chat
|--------------------------------------------------------------------------
| Diakses lewat AJAX dari widget di pojok kanan bawah, baik oleh pengunjung
| yang belum login maupun klien yang sudah masuk.
*/
Route::controller(SiteChatController::class)->prefix('chat')->name('chat.')->group(function () {
    Route::get('fetch', 'fetch')->name('fetch');
    Route::post('send', 'send')->name('send');
});

// Webhook pesan WhatsApp MASUK dari gateway (Fonnte/Wablas) -- lihat
// WhatsAppWebhookController. Alamat ini yang didaftarkan di dashboard
// gateway sebagai URL webhook.
Route::post('webhook/whatsapp', [\App\Http\Controllers\Site\WhatsAppWebhookController::class, 'handle'])->name('webhook.whatsapp');

// Link lama (/p/slug) yang sudah pernah dibagikan atau terindeks Google
// tetap diarahkan ke alamat barunya, bukan langsung 404.
Route::get('p/{slug}', function (string $slug) {
    return redirect()->route('page.show', ['slug' => $slug], 301);
});

Route::get('announcements', [SitePageController::class, 'announcementsBootstrap'])->name('announcements.index');
Route::get('announcements/{slug}', [SitePageController::class, 'announcementBootstrap'])->name('announcements.show');

/*
|--------------------------------------------------------------------------
| Webhook Pembayaran (publik)
|--------------------------------------------------------------------------
| Dipanggil oleh server gateway, bukan browser klien. Karena itu route ini
| dikecualikan dari CSRF di bootstrap/app.php. Keamanannya dijamin oleh
| verifikasi signature (Midtrans) / callback token (Xendit) di service
| masing-masing, bukan oleh session.
|
| URL yang didaftarkan di dashboard gateway:
|   Midtrans -> https://domainmu.com/payment/webhook/midtrans
|   Xendit   -> https://domainmu.com/payment/webhook/xendit
|   Duitku   -> https://domainmu.com/payment/webhook/duitku
*/
Route::post('payment/webhook/{driver}', [WebhookController::class, 'handle'])
    ->name('payment.webhook');

Route::get('payment/finish', [WebhookController::class, 'finish'])
    ->name('payment.finish');

/*
|--------------------------------------------------------------------------
| Halaman Publik (CMS) — URL bersih
|--------------------------------------------------------------------------
| SENGAJA diletakkan PALING BAWAH file ini. Laravel mencocokkan route
| berdasarkan urutan pendaftaran, dan pola {slug} di sini menangkap SATU
| segmen path apa pun (mis. /contact, /tentang-kami). Kalau route ini
| didaftarkan lebih awal, ia akan "merebut" alamat yang seharusnya milik
| route lain seperti /hosting atau /keranjang.
|
| Route multi-segmen (mis. /admin/dashboard, /client/invoice/1) otomatis
| aman — {slug} tanpa akhiran khusus tidak pernah cocok dengan path yang
| mengandung tanda "/". Rute tunggal seperti /admin dan /client sendiri
| sudah lebih dulu terdaftar di atas (lewat admin.php/client.php), jadi
| tetap diproses lebih dulu sebelum baris ini dicapai.
|
| Sebagai lapis pengaman kedua, Page::RESERVED_SLUGS mencegah slug baru
| dibuat dengan nama yang bisa bentrok sejak awal — lihat app/Models/Page.php.
*/
Route::get('{slug}', [SitePageController::class, 'showBootstrap'])
    ->name('page.show')
    ->where('slug', '[a-z0-9\-]+');
