<?php

use App\Http\Controllers\Payment\WebhookController;
use App\Http\Controllers\Site\BlogController;
use App\Http\Controllers\Site\CatalogController;
use App\Http\Controllers\Site\ChatController as SiteChatController;
use App\Http\Controllers\Site\KnowledgeBaseController;
use App\Http\Controllers\Site\PageController as SitePageController;
use App\Http\Controllers\Site\PromoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Content and Integration Routes
|--------------------------------------------------------------------------
| Editorial pages, announcements, support content, public chat, and payment
| callbacks belong to the public site. The client portal is registered
| separately on its own host in routes/client.php.
*/
Route::get('/', [CatalogController::class, 'homeBootstrap'])->name('home');

Route::get('branding/{filename}', [\App\Http\Controllers\BrandingAssetController::class, 'branding'])->name('branding.file');
Route::get('banner-image/{filename}', [\App\Http\Controllers\BrandingAssetController::class, 'banner'])->name('banner.file');
Route::get('vendor/fontawesome/css/{filename}', [\App\Http\Controllers\BrandingAssetController::class, 'fontAwesomeCss'])->name('fontawesome.css');
Route::get('vendor/fontawesome/webfonts/{filename}', [\App\Http\Controllers\BrandingAssetController::class, 'fontAwesomeWebfont'])->name('fontawesome.webfont');
Route::get('vendor/tailwind/browser.js', [\App\Http\Controllers\BrandingAssetController::class, 'tailwindBrowser'])->name('tailwind.browser');

Route::get('promo', [PromoController::class, 'index'])->name('promo.index');
Route::get('ref/{code}/{campaign?}', [\App\Http\Controllers\Site\ReferralController::class, 'visit'])->name('affiliate.visit');

Route::controller(SiteChatController::class)->prefix('chat')->name('chat.')->group(function () {
    Route::get('fetch', 'fetch')->name('fetch');
    Route::post('send', 'send')->name('send');
    Route::get('attachment/{message}/file', 'attachmentFile')->name('attachment');
});

Route::post('webhook/whatsapp', [\App\Http\Controllers\Site\WhatsAppWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhook.whatsapp');

Route::get('p/{slug}', static fn (string $slug) => redirect()->route('page.show', ['slug' => $slug], 301));

Route::get('announcements', [SitePageController::class, 'announcementsBootstrap'])->name('announcements.index');
Route::get('announcements/{slug}', [SitePageController::class, 'announcementBootstrap'])->name('announcements.show');
Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('blog/{slug}', [BlogController::class, 'show'])
    ->name('blog.show')
    ->where('slug', '[a-z0-9\-]+');
Route::get('knowledge-base', [KnowledgeBaseController::class, 'index'])->name('knowledge-base.index');
Route::get('knowledge-base/{slug}', [KnowledgeBaseController::class, 'show'])
    ->name('knowledge-base.show')
    ->where('slug', '[a-z0-9\-]+');

Route::post('payment/webhook/{driver}', [WebhookController::class, 'handle'])
    ->whereIn('driver', ['midtrans', 'xendit', 'duitku'])
    ->middleware('throttle:120,1')
    ->name('payment.webhook');
Route::match(['get', 'post'], 'payment/finish', [WebhookController::class, 'finish'])->name('payment.finish');

// Keep the public CMS slug catch-all last so it cannot shadow store routes.
Route::get('{slug}', [SitePageController::class, 'showBootstrap'])
    ->name('page.show')
    ->where('slug', '[a-z0-9\-]+');
