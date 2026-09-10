<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\OtpController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CronController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\HostingAccountController;
use App\Http\Controllers\Admin\ImpersonateController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\NavMenuController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RegistrarController;
use App\Http\Controllers\Admin\ServerController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\TldController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
| Semua route di sini otomatis diberi prefix "admin" dan
| name prefix "admin." lewat bootstrap/app.php.
|
| Order/Invoice/Hosting Account/Domain/Klien memakai pola: daftar per-status
| sebagai halaman terpisah + halaman detail + endpoint aksi terpisah
| (accept/cancel/mark-pending/notes, dst), meniru struktur WHMCS-style yang
| dicontohkan. Server/Registrar/TLD tetap pakai Route::resource karena
| tidak butuh pola status seperti ini.
*/

Route::middleware('guest:admin')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');

    // Tantangan OTP — pengguna belum login di titik ini, jadi tetap di
    // grup guest. Aksesnya dijaga oleh session "otp.admin_id".
    Route::controller(OtpController::class)->group(function () {
        Route::get('otp/challenge', 'challenge')->name('otp.challenge');
        Route::post('otp/verify', 'verify')->name('otp.verify');
        Route::post('otp/resend', 'resend')->name('otp.resend');
        Route::post('otp/cancel', 'cancel')->name('otp.cancel');
    });
});

Route::middleware('auth:admin')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class . '@indexBootstrap')->name('dashboard');
    Route::get('dashboard', DashboardController::class . '@index')->name('dashboard.alt');

    // ── Order ── (modul: sales)
    Route::middleware('module:sales')->controller(OrderController::class)->group(function () {
        Route::get('orders', 'ordersBootstrap')->name('orders');
        Route::get('pending/orders', 'pendingBootstrap')->name('orders.pending');
        Route::get('active/orders', 'activeBootstrap')->name('orders.active');
        Route::get('suspended/orders', 'suspendedBootstrap')->name('orders.suspended');
        Route::get('cancelled/orders', 'cancelledBootstrap')->name('orders.cancelled');
        Route::get('order/details/{order}', 'detailsBootstrap')->name('orders.details');

        Route::get('add/order', 'createBootstrap')->name('order.add.page');
        Route::post('add/order', 'store')->name('order.add');
        Route::get('edit/order/{order}', 'editBootstrap')->name('order.edit.page');
        Route::post('update/order/{order}', 'update')->name('order.update');
        Route::delete('delete/order/{order}', 'destroy')->name('order.delete');

        Route::post('accept/order', 'accept')->name('order.accept');
        Route::post('cancel/order', 'cancel')->name('order.cancel');
        Route::post('mark-as-pending/order', 'markPending')->name('order.mark.pending');
        Route::post('order/notes', 'orderNotes')->name('order.notes');
    });

    // ── Invoice ── (modul: billing)
    Route::middleware('module:billing')->controller(InvoiceController::class)->group(function () {
        Route::get('invoices', 'invoicesBootstrap')->name('invoices');
        Route::get('unpaid/invoices', 'unpaidBootstrap')->name('invoices.unpaid');
        Route::get('paid/invoices', 'paidBootstrap')->name('invoices.paid');
        Route::get('overdue/invoices', 'overdueBootstrap')->name('invoices.overdue');
        Route::get('cancelled/invoices', 'cancelledBootstrap')->name('invoices.cancelled');
        Route::get('invoice/details/{invoice}', 'detailsBootstrap')->name('invoices.details');
        Route::get('invoice/{invoice}/pdf', 'pdf')->name('invoices.pdf');

        Route::get('add/invoice', 'createBootstrap')->name('invoice.add.page');
        Route::post('add/invoice', 'store')->name('invoice.add');
        Route::get('edit/invoice/{invoice}', 'editBootstrap')->name('invoice.edit.page');
        Route::post('update/invoice/{invoice}', 'update')->name('invoice.update');
        Route::delete('delete/invoice/{invoice}', 'destroy')->name('invoice.delete');

        Route::post('mark-as-paid/invoice', 'markPaid')->name('invoice.mark.paid');
        Route::post('mark-as-unpaid/invoice', 'markUnpaid')->name('invoice.mark.unpaid');
        Route::post('cancel/invoice', 'cancel')->name('invoice.cancel');
        Route::post('invoice/notes', 'invoiceNotes')->name('invoice.notes');
    });

    // ── Hosting Account ── (modul: services)
    Route::middleware('module:services')->controller(HostingAccountController::class)->group(function () {
        Route::get('hosting-accounts', 'hostingAccountsBootstrap')->name('hosting-accounts');
        Route::get('pending/hosting-accounts', 'pendingBootstrap')->name('hosting-accounts.pending');
        Route::get('active/hosting-accounts', 'activeBootstrap')->name('hosting-accounts.active');
        Route::get('suspended/hosting-accounts', 'suspendedBootstrap')->name('hosting-accounts.suspended');
        Route::get('terminated/hosting-accounts', 'terminatedBootstrap')->name('hosting-accounts.terminated');
        Route::get('unlinked/hosting-accounts', 'unlinkedBootstrap')->name('hosting-accounts.unlinked');
        Route::get('hosting-account/details/{hostingAccount}', 'detailsBootstrap')->name('hosting-accounts.details');
        Route::get('hosting-account/{hostingAccount}/debug-ssl', 'debugSsl')->name('hosting-accounts.debug-ssl');
        Route::post('hosting-account/{hostingAccount}/retry', 'retryProvisioning')->name('hosting-accounts.retry');
        Route::post('hosting-account/{hostingAccount}/sync', 'syncFromServer')->name('hosting-accounts.sync');
        Route::post('hosting-account/{hostingAccount}/change-password', 'changePassword')->name('hosting-accounts.change-password');

        Route::get('add/hosting-account', 'createBootstrap')->name('hosting-account.add.page');
        Route::post('add/hosting-account', 'store')->name('hosting-account.add');
        Route::get('edit/hosting-account/{hostingAccount}', 'editBootstrap')->name('hosting-account.edit.page');
        Route::post('update/hosting-account/{hostingAccount}', 'update')->name('hosting-account.update');
        Route::delete('delete/hosting-account/{hostingAccount}', 'destroy')->name('hosting-account.delete');

        Route::post('hosting-account/{hostingAccount}/suspend', 'suspend')->name('hosting-accounts.suspend');
        Route::post('hosting-account/{hostingAccount}/unsuspend', 'unsuspend')->name('hosting-accounts.unsuspend');
        Route::post('hosting-account/{hostingAccount}/terminate', 'terminate')->name('hosting-accounts.terminate');
        Route::post('hosting-account/{hostingAccount}/cancellation/approve', 'approveCancellation')->name('hosting-accounts.cancellation.approve');
        Route::post('hosting-account/{hostingAccount}/cancellation/decline', 'declineCancellation')->name('hosting-accounts.cancellation.decline');
        Route::post('hosting-account/notes', 'notes')->name('hosting-account.notes');
        Route::post('hosting-account/{hostingAccount}/send-info', 'sendInfo')->name('hosting-accounts.send-info');
    });

    // ── Domain ── (modul: services)
    Route::middleware('module:services')->group(function () {
        Route::get('domain/search', [DomainController::class, 'searchBootstrap'])->name('domain.search');
        Route::post('domain/search', [DomainController::class, 'search']);

        Route::controller(DomainController::class)->group(function () {
            Route::get('domains', 'domainsBootstrap')->name('domains');
            Route::get('pending/domains', 'pendingBootstrap')->name('domains.pending');
            Route::get('active/domains', 'activeBootstrap')->name('domains.active');
            Route::get('expired/domains', 'expiredBootstrap')->name('domains.expired');
            Route::get('cancelled/domains', 'cancelledBootstrap')->name('domains.cancelled');
            Route::get('domain/details/{domain}', 'detailsBootstrap')->name('domains.details');

            Route::get('add/domain', 'createBootstrap')->name('domain.add.page');
            Route::post('add/domain', 'store')->name('domain.add');
            Route::get('edit/domain/{domain}', 'editBootstrap')->name('domain.edit.page');
            Route::post('update/domain/{domain}', 'update')->name('domain.update');
            Route::delete('delete/domain/{domain}', 'destroy')->name('domain.delete');

            Route::post('domain/{domain}/renew', 'renew')->name('domains.renew');
            Route::post('domain/{domain}/transfer-complete', 'markTransferComplete')->name('domains.transfer-complete');
            Route::post('domain/{domain}/restore', 'restore')->name('domains.restore');
            Route::post('domain/{domain}/retry', 'retryProvisioning')->name('domains.retry');
            Route::post('domain/{domain}/apply-default-ns', 'applyDefaultNameservers')->name('domains.apply-default-ns');
            Route::post('domain/{domain}/eligibility', 'submitEligibility')->name('domains.eligibility');
            Route::post('domain/{domain}/verify-documents', 'verifyDomainDocuments')->name('domains.verify-documents');
            Route::post('domain/{domain}/send-documents', 'sendDocumentsToRegistrar')->name('domains.send-documents');
            Route::post('domain-document/{document}/review', 'reviewDocument')->name('domain-documents.review');
            Route::get('verifikasi-berkas', [\App\Http\Controllers\Admin\DomainDocumentController::class, 'index'])
                ->name('domain-documents.index');
            Route::get('domain-document/{document}/file', 'documentFile')->name('domain-documents.file');
            Route::post('cancel/domain', 'cancel')->name('domain.cancel');
            Route::post('domain/notes', 'notes')->name('domain.notes');
        });
    });

    // ── Klien ── (modul: services)
    Route::middleware('module:services')->controller(ClientController::class)->group(function () {
        Route::get('clients', 'clientsBootstrap')->name('clients');
        Route::get('active/clients', 'activeBootstrap')->name('clients.active');
        Route::get('inactive/clients', 'inactiveBootstrap')->name('clients.inactive');
        Route::get('client/details/{client}', 'detailsBootstrap')->name('clients.details');

        Route::get('add/client', 'createBootstrap')->name('client.add.page');
        Route::post('add/client', 'store')->name('client.add');
        Route::get('edit/client/{client}', 'editBootstrap')->name('client.edit.page');
        Route::post('update/client/{client}', 'update')->name('client.update');
        Route::delete('delete/client/{client}', 'destroy')->name('client.delete');

        Route::post('client/status', 'status')->name('client.status');
        Route::post('client/notes', 'notes')->name('client.notes');
        Route::post('client/{client}/balance', 'adjustBalance')->name('client.balance.adjust');
    });

    // ── Login sebagai Klien ── (modul: services)
    // Impersonasi bisa mengubah data klien (order, profil, dsb) dengan
    // penuh, bukan sekadar melihat -- sengaja TIDAK ditambah pembatasan
    // ekstra di luar modul "services": kalau superadmin sudah memutuskan
    // seorang admin/staff boleh masuk ke modul Layanan, keputusan sampai
    // sejauh mana wewenangnya di situ sepenuhnya di tangan superadmin
    // lewat Admin & Akses, bukan dikunci lagi di kode.
    Route::middleware('module:services')
        ->post('client/{client}/impersonate', [ImpersonateController::class, 'start'])
        ->name('client.impersonate');

    // ── Server / Registrar / TLD Pricing — menyangkut kredensial
    //    infrastruktur (API token server, kunci API registrar) atau harga
    //    beli/jual TLD. Modul: infrastructure.
    Route::middleware('module:infrastructure')->group(function () {
        // ── Server / Panel Hosting (Fase 3) ──
        Route::get('vps', [\App\Http\Controllers\Admin\VpsController::class, 'index'])->name('vps');
        Route::get('add/vps', [\App\Http\Controllers\Admin\VpsController::class, 'create'])->name('vps.create');
        Route::post('add/vps', [\App\Http\Controllers\Admin\VpsController::class, 'store'])->name('vps.store');
        Route::post('vps/{vps}/retry', [\App\Http\Controllers\Admin\VpsController::class, 'retry'])->name('vps.retry');
        Route::post('vps/{vps}/power', [\App\Http\Controllers\Admin\VpsController::class, 'power'])->name('vps.power');
        Route::post('vps/{vps}/attach-ip', [\App\Http\Controllers\Admin\VpsController::class, 'attachIp'])->name('vps.attach-ip');
        Route::delete('vps/{vps}', [\App\Http\Controllers\Admin\VpsController::class, 'destroy'])->name('vps.destroy');
        Route::resource('servers', ServerController::class)->except('show');
        Route::post('servers/{server}/test-connection', [ServerController::class, 'testConnection'])->name('servers.test-connection');
        Route::post('servers/{server}/login-whm', [ServerController::class, 'loginWhm'])->name('servers.login-whm');
        Route::get('servers/{server}/diagnostics', [ServerController::class, 'diagnosticsBootstrap'])->name('servers.diagnostics');
        Route::post('servers/{server}/sync-cost', [ServerController::class, 'syncCost'])->name('servers.sync-cost');

        // ── Registrar & TLD Pricing (Fase 4) ──
        Route::resource('registrars', RegistrarController::class)->except('show');
        Route::post('registrars/{registrar}/test-connection', [RegistrarController::class, 'testConnection'])->name('registrars.test-connection');
        Route::post('registrars/{registrar}/sync-tlds', [RegistrarController::class, 'syncTlds'])->name('registrars.sync-tlds');
        Route::get('registrars/{registrar}/customers/export', [RegistrarController::class, 'exportCustomers'])->name('registrars.customers.export');
        Route::post('registrars/{registrar}/customers/import', [RegistrarController::class, 'importCustomers'])->name('registrars.customers.import');
        Route::get('registrars/{registrar}/transactions', [RegistrarController::class, 'transactionsBootstrap'])->name('registrars.transactions');
        Route::get('registrars/{registrar}/debug-balance', [RegistrarController::class, 'debugBalance'])->name('registrars.debug-balance');
        Route::get('registrars/{registrar}/diagnostics', [RegistrarController::class, 'diagnosticsBootstrap'])->name('registrars.diagnostics');

        Route::resource('tlds', TldController::class)->except('show');
        Route::post('tld/status', [TldController::class, 'status'])->name('tld.status');
        Route::post('tld/bulk-markup', [TldController::class, 'bulkMarkup'])->name('tld.bulk-markup');
        Route::post('tld/sync-preview', [TldController::class, 'syncPreview'])->name('tld.sync-preview');
        Route::post('tld/import-preview', [TldController::class, 'importPreview'])->name('tld.import-preview');
        Route::post('tld/addon-pricing', [TldController::class, 'updateAddonPricing'])->name('tlds.addon-pricing');
        Route::post('tld/import-apply', [TldController::class, 'importApply'])->name('tld.import-apply');
        Route::post('tld/bulk-update', [TldController::class, 'bulkUpdate'])->name('tld.bulk-update');

        // Halaman TLD Pricing (terpisah dari Status & Tampilan TLD di
        // atas) -- wajib pilih registrar dulu sebelum tabel harga
        // muncul, karena satu ekstensi sekarang bisa dimiliki beberapa
        // registrar sekaligus.
        Route::get('tld/pricing', [TldController::class, 'pricingBootstrap'])->name('tlds.pricing');
        Route::post('tld/pricing', [TldController::class, 'updatePricing'])->name('tld.update-pricing');

        Route::get('tld/privacy', [TldController::class, 'privacyBootstrap'])->name('tlds.privacy');
        Route::post('tld/privacy/registrars', [TldController::class, 'updatePrivacyRegistrars'])->name('tlds.privacy.registrars');
        Route::post('tld/privacy/tlds', [TldController::class, 'updatePrivacyTlds'])->name('tlds.privacy.tlds');
    });

    // ── Katalog Produk (Fase 7b) — harga jual yang memengaruhi seluruh
    //    klien. Modul: sales.
    Route::middleware('module:sales')->group(function () {
        Route::resource('product-categories', ProductCategoryController::class)->except('show');
        Route::resource('addons', \App\Http\Controllers\Admin\AddonController::class)->except('show');
        Route::post('addon/status', [\App\Http\Controllers\Admin\AddonController::class, 'status'])->name('addon.status');
        Route::resource('products', ProductController::class)->except('show');
        Route::post('product/status', [ProductController::class, 'status'])->name('product.status');

        // ── Configurable Options (Fase 8) — opsi tambahan per produk
        //    yang dipilih klien SAAT checkout (mis. "RAM Tambahan
        //    +50rb"), beda dari Addon yang dibeli belakangan lewat
        //    dashboard klien setelah hosting aktif. ──
        Route::controller(\App\Http\Controllers\Admin\ProductOptionController::class)
            ->prefix('products/{product}/options')->name('products.options.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('groups', 'storeGroup')->name('groups.store');
                Route::put('groups/{group}', 'updateGroup')->name('groups.update');
                Route::delete('groups/{group}', 'destroyGroup')->name('groups.destroy');
                Route::post('groups/{group}/status', 'statusGroup')->name('groups.status');

                Route::post('groups/{group}/options', 'storeOption')->name('options.store');
                Route::put('groups/{group}/options/{option}', 'updateOption')->name('options.update');
                Route::delete('groups/{group}/options/{option}', 'destroyOption')->name('options.destroy');
                Route::post('groups/{group}/options/{option}/status', 'statusOption')->name('options.status');
            });
    });

    // ── Pembayaran & Payment Gateway ── (modul: billing)
    Route::middleware('module:billing')->group(function () {
        Route::controller(PaymentController::class)->group(function () {
            Route::get('payments', 'paymentsBootstrap')->name('payments');
            Route::get('initiated/payments', 'initiatedBootstrap')->name('payments.initiated');
            Route::get('pending/payments', 'pendingBootstrap')->name('payments.pending');
            Route::get('paid/payments', 'paidBootstrap')->name('payments.paid');
            Route::get('failed/payments', 'failedBootstrap')->name('payments.failed');
            Route::get('refunded/payments', 'refundedBootstrap')->name('payments.refunded');
            Route::get('payment/details/{payment}', 'detailsBootstrap')->name('payments.details');

            Route::get('add/payment', 'createBootstrap')->name('payment.add.page');
            Route::post('add/payment', 'store')->name('payment.add');
            Route::delete('delete/payment/{payment}', 'destroy')->name('payment.delete');

            Route::post('approve/payment', 'approve')->name('payment.approve');
            Route::post('reject/payment', 'reject')->name('payment.reject');
            Route::post('payment/{payment}/check-status', 'checkStatus')->name('payment.check.status');
            Route::get('payment/{payment}/proof', 'proof')->name('payments.proof');
        });

        Route::controller(PaymentGatewayController::class)->group(function () {
            Route::get('gateways', 'gatewaysBootstrap')->name('gateways');
            Route::get('add/gateway', 'createBootstrap')->name('gateway.add.page');
            Route::post('add/gateway', 'store')->name('gateway.add');
            Route::get('edit/gateway/{gateway}', 'editBootstrap')->name('gateway.edit.page');
            Route::post('update/gateway/{gateway}', 'update')->name('gateway.update');
            Route::delete('delete/gateway/{gateway}', 'destroy')->name('gateway.delete');
            Route::post('gateway/status', 'status')->name('gateway.status');
        });
    });

    // ── Kupon Diskon — memengaruhi harga jual semua klien. Modul: sales
    //    (satu grup dengan Produk & Order di sidebar). ──
    Route::middleware('module:sales')->controller(CouponController::class)->group(function () {
        Route::get('coupons', 'couponsBootstrap')->name('coupons');
        Route::get('add/coupon', 'createBootstrap')->name('coupon.add.page');
        Route::post('add/coupon', 'store')->name('coupon.add');
        Route::get('edit/coupon/{coupon}', 'editBootstrap')->name('coupon.edit.page');
        Route::post('update/coupon/{coupon}', 'update')->name('coupon.update');
        Route::delete('delete/coupon/{coupon}', 'destroy')->name('coupon.delete');
        Route::post('coupon/status', 'status')->name('coupon.status');
    });

    // ── Support Ticket (Fase 6) ── (modul: support)
    Route::middleware('module:support')->controller(TicketController::class)->group(function () {
        Route::get('tickets', 'ticketsBootstrap')->name('tickets');
        Route::get('open/tickets', 'openBootstrap')->name('tickets.open');
        Route::get('answered/tickets', 'answeredBootstrap')->name('tickets.answered');
        Route::get('customer-reply/tickets', 'customerReplyBootstrap')->name('tickets.customer-reply');
        Route::get('closed/tickets', 'closedBootstrap')->name('tickets.closed');
        Route::get('ticket/details/{ticket}', 'detailsBootstrap')->name('tickets.details');
        Route::post('ticket/{ticket}/preview-transfer-code', 'previewTransferCode')->name('tickets.preview-transfer-code');
        Route::post('ticket/{ticket}/approve-transfer-code', 'approveTransferCode')->name('tickets.approve-transfer-code');

        Route::get('add/ticket', 'createBootstrap')->name('ticket.add.page');
        Route::post('add/ticket', 'store')->name('ticket.add');
        Route::delete('delete/ticket/{ticket}', 'destroy')->name('ticket.delete');

        Route::post('ticket/reply', 'reply')->name('ticket.reply');
        Route::post('ticket/close', 'close')->name('ticket.close');
        Route::post('ticket/reopen', 'reopen')->name('ticket.reopen');
        Route::post('ticket/assign', 'assign')->name('ticket.assign');
        Route::post('ticket/priority', 'priority')->name('ticket.priority');
    });

    // ── CMS: Halaman Statis, Pengumuman, Menu Navigasi — konten situs
    //    publik. Modul: content.
    Route::middleware('module:content')->group(function () {
        // ── CMS: Halaman Statis (Fase 6b) ──
        Route::controller(PageController::class)->group(function () {
            Route::get('pages', 'pagesBootstrap')->name('pages');
            Route::get('add/page', 'createBootstrap')->name('page.add.page');
            Route::post('add/page', 'store')->name('page.add');
            Route::get('edit/page/{page}', 'editBootstrap')->name('page.edit.page');
            Route::post('update/page/{page}', 'update')->name('page.update');
            Route::delete('delete/page/{page}', 'destroy')->name('page.delete');
            Route::post('page/status', 'status')->name('page.status');
            Route::post('check/slug', 'checkSlug')->name('check.slug');
        });

        // ── CMS: Pengumuman ──
        Route::controller(AnnouncementController::class)->group(function () {
            Route::get('announcements', 'announcementsBootstrap')->name('announcements');
            Route::get('add/announcement', 'createBootstrap')->name('announcement.add.page');
            Route::post('add/announcement', 'store')->name('announcement.add');
            Route::get('edit/announcement/{announcement}', 'editBootstrap')->name('announcement.edit.page');
            Route::post('update/announcement/{announcement}', 'update')->name('announcement.update');
            Route::delete('delete/announcement/{announcement}', 'destroy')->name('announcement.delete');
        });

        // ── CMS: Banner Promo ──
        Route::controller(\App\Http\Controllers\Admin\PromoBannerController::class)->prefix('promo-banners')->name('promo-banners.')->group(function () {
            Route::get('/', 'indexBootstrap')->name('index');
            Route::get('add', 'createBootstrap')->name('create');
            Route::post('add', 'store')->name('store');
            // PENTING: rute literal (status) HARUS didaftarkan SEBELUM
            // rute berwildcard ({promoBanner}) di bawahnya -- Laravel
            // mencocokkan rute berurutan dari atas, jadi kalau
            // dibalik, "status" akan tertangkap duluan oleh
            // {promoBanner} (mengira itu ID banner) dan gagal
            // route-model-binding (404), padahal rute yang dituju ada.
            Route::post('status', 'status')->name('status');
            Route::get('{promoBanner}/edit', 'editBootstrap')->name('edit');
            Route::post('{promoBanner}', 'update')->name('update');
            Route::delete('{promoBanner}', 'destroy')->name('destroy');
            Route::post('{promoBanner}/move', 'move')->name('move');
        });

        Route::controller(\App\Http\Controllers\Admin\PopupBannerController::class)->prefix('popup-banner')->name('popup-banner.')->group(function () {
            Route::get('/', 'editBootstrap')->name('edit');
            Route::post('/', 'update')->name('update');
        });

        // ── CMS: Menu Navigasi Publik ──
        Route::controller(NavMenuController::class)->group(function () {
            Route::get('nav-menus', 'indexBootstrap')->name('nav-menus');
            Route::get('add/nav-menu', 'createBootstrap')->name('nav-menu.add.page');
            Route::post('add/nav-menu', 'store')->name('nav-menu.add');
            Route::get('edit/nav-menu/{navMenu}', 'editBootstrap')->name('nav-menu.edit.page');
            Route::post('update/nav-menu/{navMenu}', 'update')->name('nav-menu.update');
            Route::delete('delete/nav-menu/{navMenu}', 'destroy')->name('nav-menu.delete');
            Route::post('nav-menu/status', 'toggleStatus')->name('nav-menu.status');
            Route::post('nav-menu/{navMenu}/move', 'move')->name('nav-menu.move');
        });
    });

    // ── Pengaturan, AI Usage, Cron Jobs — konfigurasi sistem murni.
    //    Modul: system.
    Route::middleware('module:system')->group(function () {
        // ── Pengaturan (umum, SEO, analytics, live chat) ──
        Route::controller(SettingController::class)->prefix('settings')->name('settings.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('general', 'generalBootstrap')->name('general');
            Route::post('general', 'updateGeneral')->name('general.update');
            Route::get('homepage', 'homepage')->name('homepage');
            Route::post('homepage', 'updateHomepage')->name('homepage.update');

            // Persyaratan berkas domain (menggantikan daftar hardcoded
            // di DomainDocument::requirements()).
            Route::controller(\App\Http\Controllers\Admin\DocumentRequirementController::class)
                ->prefix('persyaratan')->name('requirements.')->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('domain', 'domains')->name('domains');
                    Route::post('domain', 'updateDomain')->name('domains.update');
                    Route::get('tambah', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('{requirement}/edit', 'edit')->name('edit');
                    Route::put('{requirement}', 'update')->name('update');
                    Route::delete('{requirement}', 'destroy')->name('destroy');
                });
            Route::post('general/preset-branding', 'usePresetBranding')->name('general.preset-branding');
            Route::get('general/preset-image/{group}/{color}', 'presetImage')->name('general.preset-image');
            Route::get('seo', 'seoBootstrap')->name('seo');
            Route::post('seo', 'updateSeo')->name('seo.update');
            Route::get('branding-diagnostics', 'brandingDiagnostics')->name('branding-diagnostics');
            Route::get('pdf-invoice', 'pdfInvoice')->name('pdf-invoice');
            Route::post('pdf-invoice', 'updatePdfInvoice')->name('pdf-invoice.update');
            Route::get('pdf-invoice/preview', 'pdfInvoicePreview')->name('pdf-invoice.preview');
            Route::get('analytics', 'analyticsBootstrap')->name('analytics');
            Route::post('analytics', 'updateAnalytics')->name('analytics.update');
            Route::get('notifications', 'notificationsBootstrap')->name('notifications');
            Route::post('notifications', 'updateNotifications')->name('notifications.update');
            Route::post('notifications/test-wa', 'testWhatsApp')->name('notifications.test-wa');
            Route::post('notifications/test-sms', 'testSms')->name('notifications.test-sms');
            Route::post('notifications/test-push', 'testPush')->name('notifications.test-push');
            Route::post('notifications/vapid-keys', 'generateVapidKeys')->name('notifications.vapid-keys');
            Route::get('security', 'securityBootstrap')->name('security');
            Route::post('security', 'updateSecurity')->name('security.update');
            Route::post('security/test-recaptcha', 'testRecaptcha')->name('security.test-recaptcha');
            Route::get('livechat', 'livechatBootstrap')->name('livechat');
            Route::post('livechat', 'updateLivechat')->name('livechat.update');
            Route::post('livechat/test', 'testLiveChat')->name('livechat.test');
        });

        Route::controller(\App\Http\Controllers\Admin\AiUsageController::class)->prefix('ai-usage')->name('ai-usage.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('pricing', 'updatePricing')->name('pricing');
        });

        Route::controller(\App\Http\Controllers\Admin\SelfCpanelController::class)->prefix('self-cpanel')->name('self-cpanel.')->group(function () {
            Route::get('/', 'edit')->name('edit');
            Route::post('/', 'update')->name('update');
        });

        // ── Cron Jobs ──
        Route::controller(CronController::class)->prefix('cron')->name('cron.')->group(function () {
            Route::get('/', 'indexBootstrap')->name('index');
            Route::post('/', 'update')->name('update');
            Route::post('run/{job}', 'runNow')->name('run');
            Route::post('settings', 'saveSettings')->name('settings');
            Route::post('test-cpanel', 'testCpanel')->name('test-cpanel');
            Route::post('install-cpanel', 'installCpanel')->name('install-cpanel');
        });
    });

    // ── Template Notifikasi (isi/kata-kata tiap email & WhatsApp) — satu
    //    grup dengan CMS di sidebar. Modul: content.
    Route::middleware('module:content')
        ->controller(\App\Http\Controllers\Admin\NotificationTemplateController::class)
        ->prefix('notification-templates')->name('notification-templates.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{key}/edit', 'edit')->name('edit');
            Route::get('{key}/preview', 'preview')->name('preview');
            Route::post('{key}/preview', 'previewDraft')->name('preview.draft');
            Route::post('{key}', 'update')->name('update');
            Route::post('{key}/reset', 'reset')->name('reset');
        });

    // ── Backup & Konsol Web — satu grup dengan Server/VPS di sidebar
    //    ("Infrastruktur"). Modul: infrastructure.
    Route::middleware('module:infrastructure')->group(function () {
        // ── Backup — berisi seluruh data klien ──
        Route::controller(\App\Http\Controllers\Admin\BackupController::class)->prefix('backups')->name('backups.')->group(function () {
            Route::get('/', 'indexBootstrap')->name('index');
            Route::post('run', 'runNow')->name('run');
            Route::get('{filename}/download', 'download')->name('download');
            Route::delete('{filename}', 'destroy')->name('destroy');
            Route::post('settings', 'updateSettings')->name('settings');
            Route::post('gdrive-settings', 'updateGoogleDrive')->name('gdrive-settings');
            Route::post('gdrive-test', 'testGoogleDrive')->name('gdrive-test');
        });

        // ── Konsol Web — jalankan perintah artisan tanpa Terminal/SSH ──
        Route::controller(\App\Http\Controllers\Admin\ConsoleController::class)->prefix('console')->name('console.')->group(function () {
            Route::get('/', 'indexBootstrap')->name('index');
            Route::post('run', 'run')->name('run');
        });
    });

    // ── Manajemen Admin & Keamanan (khusus superadmin) ──
    Route::middleware('role:superadmin')->controller(AdminUserController::class)->group(function () {
        Route::get('admins', 'adminsBootstrap')->name('admins');
        Route::get('add/admin', 'createBootstrap')->name('admin.add.page');
        Route::post('add/admin', 'store')->name('admin.add');
        Route::get('edit/admin/{admin}', 'editBootstrap')->name('admin.edit.page');
        Route::post('update/admin/{admin}', 'update')->name('admin.update');
        Route::post('admin/status', 'toggleStatus')->name('admin.status');
        Route::delete('delete/admin/{admin}', 'destroy')->name('admin.delete');

        Route::get('login-attempts', 'loginAttemptsBootstrap')->name('login-attempts');
        Route::post('login-attempts/clear', 'clearAttempts')->name('login-attempts.clear');
    });

    // ── Live Chat ── (modul: support)
    Route::middleware('module:support')->controller(ChatController::class)->group(function () {
        Route::get('chats', 'indexBootstrap')->name('chats');
        Route::get('chat/{chat}', 'showBootstrap')->name('chats.show');
        Route::get('chat/{chat}/poll', 'poll')->name('chats.poll');
        Route::post('chat/{chat}/reply', 'reply')->name('chats.reply');
        Route::post('chat/{chat}/claim', 'claim')->name('chats.claim');
        Route::post('chat/{chat}/close', 'close')->name('chats.close');
        Route::post('chat/{chat}/convert-to-ticket', 'convertToTicket')->name('chats.convert-to-ticket');
        Route::delete('chat/{chat}', 'destroy')->name('chats.delete');
        Route::get('chats-global-status', 'globalStatus')->name('chats.global-status');
    });

    // ── Aktivitas & Broadcast ── (modul: system)
    Route::middleware('module:system')->controller(ActivityController::class)->group(function () {
        Route::get('activities', 'activitiesBootstrap')->name('activities');
        Route::post('activities/read-all', 'markAllRead')->name('activities.read-all');
        Route::post('activities/clear-old', 'clearOld')->name('activities.clear-old');
        Route::delete('activity/{activity}', 'destroy')->name('activity.delete');

        Route::get('promo', 'promoFormBootstrap')->name('promo');
        Route::post('promo', 'sendPromo')->name('promo.send');
    });

    // ── Profil & Keamanan Akun ──
    Route::controller(ProfileController::class)->group(function () {
        Route::get('profile', 'edit')->name('profile');
        Route::post('profile', 'update')->name('profile.update');
        Route::post('profile/password', 'updatePassword')->name('profile.password');
        Route::post('profile/two-factor', 'toggleTwoFactor')->name('profile.two-factor');
    });

    // ── Push Notification ──
    Route::controller(\App\Http\Controllers\Admin\PushSubscriptionController::class)
        ->prefix('push')->name('push.')->group(function () {
            Route::get('vapid-key', 'vapidPublicKey')->name('vapid-key');
            Route::post('subscribe', 'store')->name('subscribe');
            Route::post('unsubscribe', 'destroy')->name('unsubscribe');
        });
});