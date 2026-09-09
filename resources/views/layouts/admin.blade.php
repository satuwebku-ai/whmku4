<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') — {{ config('app.name', 'Lumora Hosting') }} <span style="display:none">[Preview Bootstrap]</span></title>

@php $adminFavicon = \App\Models\Setting::get('site_favicon'); @endphp
@if ($adminFavicon)
  <link rel="icon" type="image/png" href="{{ route('branding.file', $adminFavicon) }}">
@endif

<link rel="stylesheet" href="{{ asset('assets/css/framework.css') }}?v={{ @filemtime(public_path('assets/css/framework.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/lumora-admin.css') }}?v={{ @filemtime(public_path('assets/css/lumora-admin.css')) ?: time() }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
  :root{ --bs-font-sans-serif: 'Inter', -apple-system, sans-serif; --bs-body-font-family: var(--bs-font-sans-serif); }
  html{ font-family: var(--bs-font-sans-serif); }

  /* Posisi topbar saat collapse DIPINDAH ke class CSS (bukan inline
     style lewat JS) -- #topbar adalah anak dari #main, jadi transisinya
     otomatis konsisten dengan animasi sidebar/main yang juga berbasis
     class. Sebelumnya topbar diatur lewat style.left di JS, yang
     mengakibatkan pergerakannya sedikit tidak sinkron dengan animasi
     sidebar & konten (terasa ada jeda). */
  #main.collapsed #topbar{ left:84px!important; }

  /* Logo penuh <-> ikon kecil saat sidebar diciutkan/dibuka. */
  .sidebar-collapsed .brand-full{ display:none!important; }
  .sidebar-collapsed .brand-collapsed-icon{ display:flex!important; }
  html.sidebar-pre-collapsed #sidebar .brand-full{ display:none!important; }
  html.sidebar-pre-collapsed #sidebar .brand-collapsed-icon{ display:flex!important; }
</style>
</head>
<body class="lumora-body">
<script>
  try{ if(localStorage.getItem('lumora-layout-mode')==='horizontal'){ document.body.classList.add('mode-horizontal'); } }catch(e){}
  try{ if(localStorage.getItem('lumora-sidebar-collapsed')==='1' && !document.body.classList.contains('mode-horizontal')){ document.documentElement.classList.add('sidebar-pre-collapsed'); } }catch(e){}
</script>

<div class="d-flex min-vh-100">

  {{-- ══════════ Sidebar ══════════ --}}
  <aside id="sidebar" class="position-fixed top-0 start-0 bottom-0 d-flex flex-column border-end border-white border-opacity-10 bg-sidebar" style="width:272px;z-index:1040">

    <div id="brand-area" class="d-flex align-items-center gap-3 px-4 border-bottom border-white border-opacity-10 flex-shrink-0" style="height:64px">
      @php
        $adminLogo = \App\Models\Setting::get('site_logo');
        $brandingDisplay = \App\Models\Setting::get('branding_display', 'logo_and_text');
      @endphp
      @if ($brandingDisplay !== 'text_only')
        @if ($adminLogo)
          <img src="{{ route('branding.file', $adminLogo) }}" alt="{{ config('app.name', 'Lumora Hosting') }}"
               class="brand-full flex-shrink-0" style="height:44px;width:auto;object-fit:contain;max-width:200px">
        @else
          <div class="brand-full rounded-3 bg-accent d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;box-shadow:var(--shadow-rail)">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
          </div>
        @endif
      @endif
      @if ($brandingDisplay !== 'logo_only')
        <span class="brand-full brand-text text-white fw-bold text-nowrap" style="font-size:15px">{{ config('app.name', 'Lumora Hosting') }}</span>
      @endif

      {{-- Cuma tampil saat sidebar diciutkan -- lambang kecil saja,
           bukan logo penuh yang lebarnya tidak muat di 84px. Klik
           sidebar untuk buka lagi -- logo & tulisan lengkap muncul.
           Pakai Setting 'site_icon' (preset grup "Ikon Saja" di galeri
           logo) kalau ada -- fallback ke lambang petir bawaan kalau
           belum diatur. --}}
      @php $adminIcon = \App\Models\Setting::get('site_icon'); @endphp
      <div class="brand-collapsed-icon d-none rounded-3 bg-accent align-items-center justify-content-center flex-shrink-0 mx-auto" style="width:32px;height:32px;box-shadow:var(--shadow-rail);overflow:hidden">
        @if ($adminIcon)
          <img src="{{ route('branding.file', $adminIcon) }}" alt="" style="width:100%;height:100%;object-fit:contain">
        @else
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
        @endif
      </div>
    </div>

    <nav class="sidebar-scroll flex-grow-1 overflow-y-auto px-3 py-4">
      <p class="menu-eyebrow text-uppercase small fw-semibold mb-3 mt-1" style="font-size:11px;letter-spacing:.14em;color:#64748b!important">Menu Utama</p>

      <ul class="nav flex-column gap-1" style="font-size:13px">
        @php
          // Dikelompokkan berdasarkan fungsi -- item dengan 'route'
          // adalah tautan tunggal, item dengan 'children' adalah grup
          // yang punya submenu.
          $groups = [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'match' => ['admin.dashboard*'], 'icon' => 'M3 3h7v9H3zM14 3h7v5h-7zM14 10h7v11h-7zM3 14h7v7H3z'],

            ['label' => 'Penjualan', 'icon' => 'M20 7 12 3 4 7m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'module' => 'sales', 'children' => [
              ['label' => 'Produk', 'route' => 'admin.products.index', 'match' => ['admin.products.*', 'admin.product-categories.*', 'admin.product.*', 'admin.addons.*', 'admin.addon.*']],
              ['label' => 'Order',  'route' => 'admin.orders', 'match' => ['admin.order*']],
              ['label' => 'Kupon',  'route' => 'admin.coupons', 'match' => ['admin.coupon*']],
            ]],

            ['label' => 'Billing', 'icon' => 'M2 7h20v10H2zM2 10h20M6 15h4', 'module' => 'billing', 'children' => [
              ['label' => 'Invoice',     'route' => 'admin.invoices', 'match' => ['admin.invoice*']],
              ['label' => 'Pembayaran',  'route' => 'admin.payments', 'match' => ['admin.payment*', 'admin.gateway*']],
            ]],

            ['label' => 'Layanan', 'icon' => 'M22 12H2M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z', 'module' => 'services', 'children' => [
              ['label' => 'Klien',            'route' => 'admin.clients', 'match' => ['admin.client*']],
              ['label' => 'Hosting Account',  'route' => 'admin.hosting-accounts', 'match' => ['admin.hosting-account*']],
              ['label' => 'Domain',           'route' => 'admin.domains', 'match' => ['admin.domain*', 'admin.tlds.*', 'admin.registrars.*']],
              ['label' => 'Verifikasi Berkas','route' => 'admin.domain-documents.index', 'match' => ['admin.domain-documents.*']],
            ]],

            ['label' => 'Infrastruktur', 'icon' => 'M4 4h16v6H4zM4 14h16v6H4zM8 8h.01M8 18h.01', 'module' => 'infrastructure', 'children' => [
              ['label' => 'Server',      'route' => 'admin.servers.index', 'match' => ['admin.servers*']],
              ['label' => 'Layanan VPS', 'route' => 'admin.vps', 'match' => ['admin.vps*']],
              ['label' => 'Backup',      'route' => 'admin.backups.index', 'match' => ['admin.backups.*']],
              ['label' => 'Konsol Web',  'route' => 'admin.console.index', 'match' => ['admin.console.*']],
            ]],

            ['label' => 'Dukungan', 'icon' => 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z', 'module' => 'support', 'children' => [
              ['label' => 'Live Chat',        'route' => 'admin.chats', 'match' => ['admin.chats*'], 'chat_badge' => true],
              ['label' => 'Support / Tiket',  'route' => 'admin.tickets', 'match' => ['admin.ticket*']],
            ]],

            ['label' => 'Konten', 'icon' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15Z', 'module' => 'content', 'children' => [
              ['label' => 'Konten & Halaman',     'route' => 'admin.pages', 'match' => ['admin.page*', 'admin.announcement*', 'admin.promo-banners.*', 'admin.popup-banner.*']],
              ['label' => 'Template Notifikasi',  'route' => 'admin.notification-templates.index', 'match' => ['admin.notification-templates.*']],
            ]],

            ['label' => 'Sistem', 'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 0 1 4 0v.09c0 .67.39 1.28 1 1.51.63.24 1.35.12 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06c-.45.47-.57 1.19-.33 1.82.23.61.84 1 1.51 1H21a2 2 0 0 1 0 4h-.09c-.67 0-1.28.39-1.51 1Z', 'module' => 'system', 'children' => [
              ['label' => 'Admin & Akses', 'route' => 'admin.admins', 'match' => ['admin.admins*', 'admin.admin.*', 'admin.login-attempts*'], 'superadmin' => true],
              ['label' => 'Aktivitas',     'route' => 'admin.activities', 'match' => ['admin.activities*', 'admin.activity*', 'admin.promo*']],
              ['label' => 'Pengaturan',    'route' => 'admin.settings.general', 'match' => ['admin.settings.*']],
            ]],
          ];

          // Grup terbuka otomatis kalau salah satu anaknya sedang aktif
          // -- supaya klien langsung lihat konteksnya tanpa perlu klik.
          $isChildActive = fn ($children) => collect($children)->contains(
              fn ($c) => $c['match'] && request()->routeIs(...$c['match'])
          );
        @endphp

        @foreach ($groups as $group)
          @continue(!empty($group['superadmin']) && ! auth('admin')->user()?->isSuperadmin())
          @continue(!empty($group['module']) && ! auth('admin')->user()?->hasModule($group['module']))

          @if (isset($group['route']))
            {{-- Item tunggal, tanpa submenu (mis. Dashboard) --}}
            <li class="menu-item">
              <a href="{{ route($group['route']) }}" data-label="{{ $group['label'] }}"
                 class="nav-item-link w-100 btn d-flex align-items-center gap-3 px-3 py-2 rounded-3 border-0 text-start text-decoration-none
                        {{ $group['match'] && request()->routeIs(...$group['match']) ? 'active text-white' : 'text-white-50' }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-shrink-0"><path d="{{ $group['icon'] }}"/></svg>
                <span class="label-text text-nowrap">{{ $group['label'] }}</span>
              </a>
            </li>
          @else
            @php
              // Anak dengan superadmin sendiri difilter dulu (module
              // sudah dicek di level grup di atas, jadi tidak perlu
              // dicek lagi per-anak) -- supaya grup yang SEMUA anaknya
              // tersembunyi tidak menampilkan grup kosong tanpa isi.
              $visibleChildren = collect($group['children'])->filter(function ($c) {
                  if (!empty($c['superadmin']) && ! auth('admin')->user()?->isSuperadmin()) return false;
                  return true;
              });
            @endphp
            @continue($visibleChildren->isEmpty())

            @php $groupActive = $isChildActive($visibleChildren); @endphp
            <li class="menu-item {{ $groupActive ? 'open' : '' }}">
              <button type="button" data-label="{{ $group['label'] }}" aria-expanded="{{ $groupActive ? 'true' : 'false' }}"
                      class="menu-trigger nav-item-link w-100 btn d-flex align-items-center justify-content-between gap-3 px-3 py-2 rounded-3 border-0 text-start
                             {{ $groupActive ? 'active text-white' : 'text-white-50' }}">
                <span class="d-flex align-items-center gap-3">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-shrink-0"><path d="{{ $group['icon'] }}"/></svg>
                  <span class="label-text text-nowrap">{{ $group['label'] }}</span>
                </span>
                <svg class="chevron flex-shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
              </button>
              <ul class="submenu nav flex-column ms-2 ps-3 border-start border-white border-opacity-10 {{ $groupActive ? 'open' : '' }}">
                @foreach ($visibleChildren as $child)
                  <li>
                    <a href="{{ route($child['route']) }}"
                       class="nav-link px-3 py-2 rounded-2 d-flex align-items-center justify-content-between {{ $child['match'] && request()->routeIs(...$child['match']) ? 'active' : '' }}">
                      {{ $child['label'] }}
                      @if (! empty($child['chat_badge']))
                        <span id="chatSidebarBadge" class="d-none badge rounded-pill bg-danger" style="font-size:10px">0</span>
                      @endif
                    </a>
                  </li>
                @endforeach
              </ul>
            </li>
          @endif
        @endforeach
      </ul>
    </nav>

    <div id="hNavRight" class="d-none flex-shrink-0 align-items-center gap-1 pe-3 ps-2 border-start border-white border-opacity-10">
      <button id="hNavScrollLeft" class="btn btn-sm text-white-50 border-0" style="width:28px;height:28px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg></button>
      <button id="hNavScrollRight" class="btn btn-sm text-white-50 border-0" style="width:28px;height:28px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg></button>
    </div>

    <div class="help-box px-4 py-3 m-3 mt-0 rounded-3 border border-white border-opacity-10 flex-shrink-0" style="background:rgba(255,255,255,.05)">
      <p class="text-white fw-semibold mb-1" style="font-size:13px">Butuh Bantuan?</p>
      <p class="text-white-50 mb-0" style="font-size:11px;line-height:1.6">Modul hosting &amp; billing lain akan ditambahkan bertahap.</p>
    </div>
  </aside>

  <div id="sidebarBackdrop"></div>

  {{-- ══════════ Main content ══════════ --}}
  <div id="main" class="main-shifted flex-grow-1 min-vh-100 d-flex flex-column" style="padding-top:64px;overflow-x:hidden;min-width:0">

    <header id="topbar" class="d-flex align-items-center justify-content-between px-4 position-fixed top-0 end-0 bg-topbar" style="height:64px;left:272px;z-index:1041">
      <div class="d-flex align-items-center gap-2">
        <a href="{{ route('admin.dashboard') }}" id="topbarBrand" class="d-none align-items-center gap-2 pe-3 me-1 border-end border-white border-opacity-25 text-decoration-none">
          @if ($brandingDisplay !== 'text_only')
            @if ($adminLogo)
              <img src="{{ route('branding.file', $adminLogo) }}" alt="{{ config('app.name', 'Lumora Hosting') }}" style="height:26px;width:auto;object-fit:contain;max-width:140px" class="flex-shrink-0">
            @else
              <div class="rounded-3 bg-accent d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;box-shadow:var(--shadow-rail)">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
              </div>
            @endif
          @endif
          @if ($brandingDisplay !== 'logo_only')
            <span class="text-white fw-bold text-nowrap" style="font-size:14px">{{ config('app.name', 'Lumora Hosting') }}</span>
          @endif
        </a>

        <button id="collapseBtn" class="topbar-icon-btn btn d-flex align-items-center justify-content-center">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <button id="mobileMenuBtn" class="topbar-icon-btn btn align-items-center justify-content-center" style="display:none">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>

        <div class="position-relative d-none d-sm-block" id="searchWrapper">
          <div class="d-flex align-items-center gap-2 rounded-3 px-3 py-2" style="background:rgba(255,255,255,.1);width:16rem">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white-50 flex-shrink-0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input id="topbarSearch" type="text" placeholder="Cari klien, order, invoice..." class="bg-transparent border-0 text-white w-100" style="outline:none;font-size:13px">
          </div>
          {{-- Pencarian cepat -- tautan langsung ke bagian yang paling sering dicari, bukan pencarian database sungguhan (itu perlu endpoint AJAX tersendiri, bisa ditambah kalau perlu). --}}
          <div id="searchDropdown" class="d-none position-absolute top-100 mt-2 start-0 bg-white rounded-3 border shadow-lg overflow-hidden" style="width:20rem;z-index:1050">
            <div class="px-3 py-2 border-bottom"><p class="text-uppercase text-muted mb-0 fw-semibold" style="font-size:11px">Pencarian Cepat</p></div>
            <div class="py-1">
              <a href="{{ route('admin.clients') }}" class="d-flex align-items-center gap-3 px-3 py-2 text-decoration-none">
                <span class="rounded-3 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px"><i class="fa-solid fa-users text-accent" style="font-size:13px"></i></span>
                <div><p class="mb-0 small fw-medium text-dark">Klien</p><p class="mb-0 text-muted" style="font-size:11px">Cari &amp; kelola akun klien</p></div>
              </a>
              <a href="{{ route('admin.invoices') }}" class="d-flex align-items-center gap-3 px-3 py-2 text-decoration-none">
                <span class="rounded-3 bg-success bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px"><i class="fa-solid fa-file-invoice text-success" style="font-size:13px"></i></span>
                <div><p class="mb-0 small fw-medium text-dark">Invoice</p><p class="mb-0 text-muted" style="font-size:11px">Tagihan &amp; pembayaran</p></div>
              </a>
              <a href="{{ route('admin.domains') }}" class="d-flex align-items-center gap-3 px-3 py-2 text-decoration-none">
                <span class="rounded-3 bg-warning bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px"><i class="fa-solid fa-globe text-warning" style="font-size:13px"></i></span>
                <div><p class="mb-0 small fw-medium text-dark">Domain</p><p class="mb-0 text-muted" style="font-size:11px">Kelola domain klien</p></div>
              </a>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex align-items-center gap-1">
        @php
          $unreadActivities = \App\Models\ActivityLog::unread()->count();
          $recentActivities = \App\Models\ActivityLog::latest()->limit(6)->get();

          $levelStyle = fn ($level) => match ($level) {
              'danger'  => ['bg' => 'bg-danger', 'icon' => 'fa-circle-exclamation'],
              'warning' => ['bg' => 'bg-warning', 'icon' => 'fa-triangle-exclamation'],
              'success' => ['bg' => 'bg-success', 'icon' => 'fa-circle-check'],
              default   => ['bg' => 'bg-secondary', 'icon' => 'fa-circle-info'],
          };

          // Statistik ringkas untuk dropdown Widgets -- angka SUNGGUHAN
          // dari database, bukan contoh statis.
          $widgetStats = [
              ['label' => 'Klien Aktif', 'value' => \App\Models\Client::count(), 'icon' => 'fa-users', 'color' => 'primary', 'bg' => 'rgba(99,102,241,.05),#eef2ff'],
              ['label' => 'Hosting Aktif', 'value' => \App\Models\HostingAccount::where('status', 'active')->count(), 'icon' => 'fa-server', 'color' => 'success', 'bg' => '#ecfdf5,#f0fdfa'],
              ['label' => 'Invoice Belum Bayar', 'value' => \App\Models\Invoice::where('status', 'unpaid')->count(), 'icon' => 'fa-file-invoice', 'color' => 'warning', 'bg' => '#fffbeb,#fff7ed'],
              ['label' => 'Tiket Terbuka', 'value' => \App\Models\Ticket::whereIn('status', ['open', 'customer_reply'])->count(), 'icon' => 'fa-headset', 'color' => 'danger', 'bg' => '#fff1f2,#fdf2f8'],
          ];

          // "Pesan" diadaptasi jadi percakapan Live Chat terbaru -- Lumora
          // tidak punya sistem pesan antar-admin, jadi ini yang paling
          // relevan dari data yang benar-benar ada.
          $recentChats = \App\Models\ChatConversation::with('client')->latest('last_message_at')->limit(3)->get();
        @endphp

        {{-- Apps Grid -- tautan cepat ke bagian utama, dikelompokkan
             beda dari menu sidebar supaya jadi jalan pintas genuinely
             berguna, bukan sekadar duplikat sidebar. --}}
        <div class="dropdown">
          <button class="topbar-icon-btn btn d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
          </button>
          <div class="dropdown-menu dropdown-menu-end p-0 rounded-3 overflow-hidden" style="width:18rem">
            <div class="px-3 py-2 border-bottom"><p class="mb-0 small fw-semibold text-dark">Akses Cepat</p></div>
            <div class="row row-cols-3 g-1 p-2 m-0">
              <a href="{{ route('admin.dashboard') }}" class="col text-center text-decoration-none py-2 rounded-3">
                <span class="rounded-3 bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-1" style="width:40px;height:40px"><i class="fa-solid fa-gauge text-accent"></i></span>
                <div class="small fw-medium text-secondary">Dashboard</div>
              </a>
              <a href="{{ route('admin.clients') }}" class="col text-center text-decoration-none py-2 rounded-3">
                <span class="rounded-3 bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-1" style="width:40px;height:40px"><i class="fa-solid fa-users text-success"></i></span>
                <div class="small fw-medium text-secondary">Klien</div>
              </a>
              <a href="{{ route('admin.invoices') }}" class="col text-center text-decoration-none py-2 rounded-3">
                <span class="rounded-3 bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-1" style="width:40px;height:40px"><i class="fa-solid fa-file-invoice text-warning"></i></span>
                <div class="small fw-medium text-secondary">Invoice</div>
              </a>
              <a href="{{ route('admin.hosting-accounts') }}" class="col text-center text-decoration-none py-2 rounded-3">
                <span class="rounded-3 bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-1" style="width:40px;height:40px"><i class="fa-solid fa-server text-danger"></i></span>
                <div class="small fw-medium text-secondary">Hosting</div>
              </a>
              <a href="{{ route('admin.domains') }}" class="col text-center text-decoration-none py-2 rounded-3">
                <span class="rounded-3 bg-info bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-1" style="width:40px;height:40px"><i class="fa-solid fa-globe text-info"></i></span>
                <div class="small fw-medium text-secondary">Domain</div>
              </a>
              <a href="{{ route('admin.settings.general') }}" class="col text-center text-decoration-none py-2 rounded-3">
                <span class="rounded-3 bg-secondary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-1" style="width:40px;height:40px"><i class="fa-solid fa-gear text-secondary"></i></span>
                <div class="small fw-medium text-secondary">Pengaturan</div>
              </a>
            </div>
          </div>
        </div>

        {{-- Widgets -- ringkasan angka penting, data sungguhan. --}}
        <div class="dropdown">
          <button class="topbar-icon-btn btn d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="8" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><path d="M13 17h8M17 13v8"/></svg>
          </button>
          <div class="dropdown-menu dropdown-menu-end p-0 rounded-3 overflow-hidden" style="width:20rem">
            <div class="px-3 py-2 border-bottom"><p class="mb-0 small fw-semibold text-dark">Ringkasan</p></div>
            <div class="p-2 d-flex flex-column gap-2">
              @foreach ($widgetStats as $w)
                <div class="d-flex align-items-center gap-3 p-2 rounded-3 border" style="background:linear-gradient(to right,{{ $w['bg'] }})">
                  <div class="rounded-3 bg-{{ $w['color'] }} bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px">
                    <i class="fa-solid {{ $w['icon'] }} text-{{ $w['color'] === 'primary' ? 'accent' : $w['color'] }}"></i>
                  </div>
                  <div class="flex-grow-1 min-w-0"><p class="mb-0 small fw-semibold text-dark">{{ $w['label'] }}</p><p class="mb-0 fw-bold text-{{ $w['color'] === 'primary' ? 'accent' : $w['color'] }}">{{ $w['value'] }}</p></div>
                </div>
              @endforeach
            </div>
          </div>
        </div>

        {{-- Messages -- percakapan Live Chat terbaru (data sungguhan). --}}
        <div class="dropdown">
          <button class="topbar-icon-btn btn d-flex align-items-center justify-content-center position-relative" type="button" data-bs-toggle="dropdown">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          </button>
          <div class="dropdown-menu dropdown-menu-end p-0 rounded-3 overflow-hidden" style="width:20rem">
            <div class="px-3 py-2 border-bottom d-flex align-items-center justify-content-between">
              <p class="mb-0 small fw-semibold text-dark">Live Chat Terbaru</p>
            </div>
            @forelse ($recentChats as $chat)
              <a href="{{ route('admin.chats.show', $chat) }}" class="d-flex align-items-start gap-3 px-3 py-2 text-decoration-none border-bottom">
                <div class="avatar avatar-sm">{{ $chat->initials }}</div>
                <div class="flex-grow-1 min-w-0">
                  <div class="d-flex align-items-center justify-content-between gap-2"><p class="mb-0 small fw-semibold text-dark text-truncate">{{ $chat->display_name }}</p><p class="mb-0 text-muted flex-shrink-0" style="font-size:11px">{{ $chat->last_message_at?->diffForHumans() }}</p></div>
                  <p class="mb-0 text-muted text-truncate mt-1" style="font-size:12px">{{ \Illuminate\Support\Str::limit(optional($chat->messages()->latest('id')->first())->message ?? 'Lampiran', 50) }}</p>
                </div>
              </a>
            @empty
              <p class="text-center text-muted small py-4 mb-0">Belum ada percakapan.</p>
            @endforelse
            <div class="px-3 py-2 bg-light border-top text-center"><a href="{{ route('admin.chats') }}" class="text-accent text-decoration-none small fw-medium">Lihat Semua Live Chat</a></div>
          </div>
        </div>

        <div class="dropdown">
          <button class="topbar-icon-btn btn d-flex align-items-center justify-content-center position-relative" type="button" data-bs-toggle="dropdown">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            @if ($unreadActivities > 0)
              <span class="position-absolute bg-danger rounded-circle border border-white text-white d-flex align-items-center justify-content-center fw-bold" style="width:16px;height:16px;font-size:9px;top:2px;right:2px">
                {{ $unreadActivities > 9 ? '9+' : $unreadActivities }}
              </span>
            @endif
          </button>
          <div class="dropdown-menu dropdown-menu-end p-0 rounded-3 overflow-hidden" style="width:20rem">
            <div class="px-3 py-2 border-bottom d-flex align-items-center justify-content-between">
              <p class="mb-0 small fw-semibold text-dark">Notifikasi</p>
              @if ($unreadActivities > 0)
                <span class="badge badge-soft-danger rounded-pill" style="font-size:10px">{{ $unreadActivities }} Baru</span>
              @endif
            </div>
            <div style="max-height:18rem;overflow-y:auto">
              @forelse ($recentActivities as $activity)
                @php $style = $levelStyle($activity->level); @endphp
                <a href="{{ $activity->link ?: route('admin.activities') }}"
                   class="d-flex align-items-start gap-3 px-3 py-2 border-bottom text-decoration-none {{ ! $activity->read_at ? 'bg-primary bg-opacity-10' : '' }}">
                  <span class="rounded-circle {{ $style['bg'] }} bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px">
                    <i class="fa-solid {{ $style['icon'] }}" style="font-size:13px;color:var(--bs-{{ str_replace('bg-', '', $style['bg']) }})"></i>
                  </span>
                  <div class="flex-grow-1 min-w-0">
                    <p class="mb-0 small fw-semibold text-dark">{{ $activity->title }}</p>
                    <p class="mb-0 text-muted text-truncate mt-1" style="font-size:12px">{{ $activity->description }}</p>
                    <p class="mb-0 text-muted mt-1" style="font-size:11px">{{ $activity->created_at->diffForHumans() }}</p>
                  </div>
                </a>
              @empty
                <p class="text-center text-muted small py-4 mb-0">Belum ada notifikasi.</p>
              @endforelse
            </div>
            <div class="px-3 py-2 bg-light border-top text-center">
              <a href="{{ route('admin.activities') }}" class="text-accent text-decoration-none small fw-medium">Lihat Semua Notifikasi</a>
            </div>
          </div>
        </div>

        {{-- Layout Toggle -- ganti antara sidebar vertikal (default) dan
             menu horizontal di bagian atas. --}}
        <button id="layoutToggleBtn" title="Ganti Tampilan Layout">
          <svg id="layoutIcon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/></svg>
          <span id="layoutLabel">Horizontal</span>
        </button>

        <div class="border-start border-white border-opacity-25 mx-1" style="height:1.5rem"></div>

        <div class="dropdown">
          <button class="btn d-flex align-items-center gap-2 px-2 py-1 rounded-3" type="button" data-bs-toggle="dropdown" style="background:transparent;border:0">
            <img src="{{ auth('admin')->user()->avatar_url }}" class="rounded-circle" style="width:32px;height:32px;border:2px solid rgba(255,255,255,.2)" alt="avatar">
            <span class="d-none d-md-block text-start">
              <span class="d-block text-white fw-semibold lh-sm" style="font-size:13px">{{ auth('admin')->user()->name }}</span>
              <span class="d-block text-white-50 text-capitalize lh-sm" style="font-size:11px">{{ auth('admin')->user()->role }}</span>
            </span>
          </button>
          <div class="dropdown-menu dropdown-menu-end rounded-3 overflow-hidden" style="width:14rem">
            <div class="px-3 py-2 border-bottom">
              <p class="mb-0 small fw-semibold text-dark">{{ auth('admin')->user()->name }}</p>
              <p class="mb-0 text-muted" style="font-size:11px">{{ auth('admin')->user()->email }}</p>
            </div>
            <a href="{{ route('admin.profile') }}" class="dropdown-item d-flex align-items-center gap-2 small py-2">
              <i class="fa-regular fa-user text-muted"></i> Profil Saya
            </a>
            <a href="{{ route('admin.profile') }}" class="dropdown-item d-flex align-items-center gap-2 small py-2">
              <i class="fa-solid fa-shield-halved text-muted"></i> Keamanan &amp; 2FA
            </a>
            <div class="border-top">
              <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 small py-2 text-danger">
                  <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="flex-grow-1 p-4">
      @yield('content')
    </main>
  </div>
</div>

<script src="{{ asset('assets/js/framework.js') }}?v={{ @filemtime(public_path('assets/js/framework.js')) ?: time() }}"></script>
<script>
  const sidebarEl = document.getElementById('sidebar');
  const mainEl = document.getElementById('main');

  // Status collapse DIINGAT lewat localStorage -- supaya tidak balik ke
  // kondisi awal tiap kali pindah halaman (karena ini website biasa,
  // tiap klik menu = reload halaman baru, bukan single-page app).
  if (localStorage.getItem('lumora-sidebar-collapsed') === '1') {
    sidebarEl.classList.add('sidebar-collapsed');
    mainEl.classList.add('collapsed');
  }

  document.getElementById('collapseBtn')?.addEventListener('click', () => {
    const collapsed = sidebarEl.classList.toggle('sidebar-collapsed');
    mainEl.classList.toggle('collapsed', collapsed);
    localStorage.setItem('lumora-sidebar-collapsed', collapsed ? '1' : '0');
  });

  // Mobile: sidebar off-canvas + backdrop.
  const mobileBtn = document.getElementById('mobileMenuBtn');
  const backdropEl = document.getElementById('sidebarBackdrop');

  function toggleMobileSidebar(open) {
    sidebarEl.classList.toggle('mobile-open', open);
    backdropEl.classList.toggle('visible', open);
  }
  mobileBtn?.addEventListener('click', () => toggleMobileSidebar(! sidebarEl.classList.contains('mobile-open')));
  backdropEl?.addEventListener('click', () => toggleMobileSidebar(false));

  /* ══════════ Submenu — toggle & accordion ══════════
     Klik menu-trigger membuka/menutup submenunya, dan otomatis menutup
     menu lain yang sejajar (accordion) -- persis seperti file referensi. */
  document.querySelectorAll('.menu-trigger').forEach(btn => {
    const item = btn.closest('.menu-item');
    const submenu = item ? item.querySelector('.submenu') : null;
    if (!item || !submenu) return;

    btn.addEventListener('click', () => {
      const isOpen = item.classList.contains('open');
      const parentUl = item.parentElement;

      parentUl.querySelectorAll(':scope > .menu-item.open').forEach(sib => {
        if (sib !== item) {
          sib.classList.remove('open');
          sib.querySelector('.submenu')?.classList.remove('open');
          sib.querySelector('.menu-trigger')?.setAttribute('aria-expanded', 'false');
        }
      });

      item.classList.toggle('open', !isOpen);
      submenu.classList.toggle('open', !isOpen);
      btn.setAttribute('aria-expanded', String(!isOpen));
    });
  });

  /* ══════════ Flyout / tooltip untuk mode sidebar collapsed ══════════
     Saat sidebar diciutkan, submenu inline disembunyikan total, diganti
     panel "flyout" yang muncul di samping ikon saat hover. Item tanpa
     submenu cukup tooltip label saja. */
  const tooltipEl = document.createElement('div');
  tooltipEl.id = 'sidebarTooltip';
  document.body.appendChild(tooltipEl);
  const flyoutEl = document.createElement('div');
  flyoutEl.id = 'sidebarFlyout';
  document.body.appendChild(flyoutEl);
  let flyoutHideTimer = null;

  function showTooltip(target) {
    if (!sidebarEl.classList.contains('sidebar-collapsed')) return;
    const label = target.getAttribute('data-label');
    if (!label) return;
    const rect = target.getBoundingClientRect();
    tooltipEl.textContent = label;
    tooltipEl.style.left = (rect.right + 14) + 'px';
    tooltipEl.style.top = (rect.top + rect.height / 2) + 'px';
    tooltipEl.classList.add('visible');
  }
  function hideTooltip() { tooltipEl.classList.remove('visible'); }

  function showFlyout(menuItem, target) {
    if (!sidebarEl.classList.contains('sidebar-collapsed')) return;
    clearTimeout(flyoutHideTimer);
    const submenu = menuItem.querySelector('.submenu');
    if (!submenu) return;
    const label = target.getAttribute('data-label') || '';
    const links = submenu.querySelectorAll('a');
    let html = '<div class="flyout-title">' + label + '</div>';
    links.forEach(a => { html += '<a href="' + a.getAttribute('href') + '">' + a.textContent.trim() + '</a>'; });
    flyoutEl.innerHTML = html;
    const rect = target.getBoundingClientRect();
    flyoutEl.style.left = (rect.right + 14) + 'px';
    flyoutEl.style.top = rect.top + 'px';
    flyoutEl.classList.add('visible');
  }
  function scheduleHideFlyout() {
    clearTimeout(flyoutHideTimer);
    flyoutHideTimer = setTimeout(() => flyoutEl.classList.remove('visible'), 150);
  }

  document.querySelectorAll('.menu-item').forEach(menuItem => {
    const link = menuItem.querySelector('.nav-item-link');
    if (!link || !link.hasAttribute('data-label')) return;
    const hasSubmenu = !!menuItem.querySelector('.submenu');
    if (hasSubmenu) {
      menuItem.addEventListener('mouseenter', () => showFlyout(menuItem, link));
      menuItem.addEventListener('mouseleave', scheduleHideFlyout);
    } else {
      link.addEventListener('mouseenter', () => showTooltip(link));
      link.addEventListener('mouseleave', hideTooltip);
    }
  });
  flyoutEl.addEventListener('mouseenter', () => clearTimeout(flyoutHideTimer));
  flyoutEl.addEventListener('mouseleave', scheduleHideFlyout);
  document.querySelector('.sidebar-scroll')?.addEventListener('scroll', () => {
    hideTooltip();
    flyoutEl.classList.remove('visible');
  });

  /* ══════════════════════════════════════════════════════════════
     Layout toggle: vertical <-> horizontal
     ══════════════════════════════════════════════════════════════ */
  const layoutBtn = document.getElementById('layoutToggleBtn');
  const layoutLabel = document.getElementById('layoutLabel');

  function setLayoutMode(mode) {
    const horizontal = mode === 'horizontal';
    document.body.classList.toggle('mode-horizontal', horizontal);
    layoutLabel.textContent = horizontal ? 'Vertikal' : 'Horizontal';
    if (horizontal) {
      sidebarEl.classList.remove('sidebar-collapsed');
      mainEl.classList.remove('collapsed');
      closeAllHSubmenus();
      setTimeout(setupHorizontalSubmenus, 50);
    } else {
      closeAllHSubmenus();
    }
    try { localStorage.setItem('lumora-layout-mode', horizontal ? 'horizontal' : 'vertical'); } catch (e) {}
  }
  layoutBtn?.addEventListener('click', () => {
    setLayoutMode(document.body.classList.contains('mode-horizontal') ? 'vertical' : 'horizontal');
  });
  // Terapkan mode tersimpan saat halaman dimuat.
  try {
    if (localStorage.getItem('lumora-layout-mode') === 'horizontal') setLayoutMode('horizontal');
  } catch (e) {}

  document.getElementById('hNavScrollLeft')?.addEventListener('click', () => {
    document.querySelector('#sidebar .sidebar-scroll').scrollBy({ left: -200, behavior: 'smooth' });
  });
  document.getElementById('hNavScrollRight')?.addEventListener('click', () => {
    document.querySelector('#sidebar .sidebar-scroll').scrollBy({ left: 200, behavior: 'smooth' });
  });

  /* ══════════════════════════════════════════════════════════════
     Submenu di mode horizontal — dibuka via hover, posisi dihitung
     lewat getBoundingClientRect() + position:fixed supaya tidak
     ke-clip oleh overflow-x:auto pada nav.
     ══════════════════════════════════════════════════════════════ */
  let hFlyoutTimer = null;
  function resetHSubmenuStyle(s) {
    s.classList.remove('h-open');
    ['top', 'left'].forEach(p => s.style[p] = '');
  }
  function setupHorizontalSubmenus() {
    if (!document.body.classList.contains('mode-horizontal')) return;
    document.querySelectorAll('#sidebar .menu-item').forEach(item => {
      const submenu = item.querySelector('.submenu');
      if (!submenu || item.dataset.hListenersAttached) return;
      item.dataset.hListenersAttached = '1';
      item.addEventListener('mouseenter', () => {
        if (!document.body.classList.contains('mode-horizontal')) return;
        clearTimeout(hFlyoutTimer);
        document.querySelectorAll('#sidebar .submenu.h-open').forEach(s => { if (s !== submenu) resetHSubmenuStyle(s); });
        const rect = item.getBoundingClientRect();
        submenu.style.top = (rect.bottom + 6) + 'px';
        submenu.style.left = rect.left + 'px';
        submenu.classList.add('h-open');
      });
      item.addEventListener('mouseleave', () => {
        if (!document.body.classList.contains('mode-horizontal')) return;
        hFlyoutTimer = setTimeout(() => resetHSubmenuStyle(submenu), 150);
      });
      submenu.addEventListener('mouseenter', () => clearTimeout(hFlyoutTimer));
      submenu.addEventListener('mouseleave', () => {
        if (!document.body.classList.contains('mode-horizontal')) return;
        hFlyoutTimer = setTimeout(() => resetHSubmenuStyle(submenu), 150);
      });
    });
  }
  function closeAllHSubmenus() {
    document.querySelectorAll('#sidebar .submenu').forEach(s => resetHSubmenuStyle(s));
  }

  // Tutup sidebar mobile otomatis saat memilih link submenu.
  document.querySelectorAll('.submenu a, #sidebar > nav > ul > li > a.nav-item-link').forEach(a => {
    a.addEventListener('click', () => { if (window.innerWidth < 992) toggleMobileSidebar(false); });
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth >= 992) toggleMobileSidebar(false);
  });

  /* ══════════════════════════════════════════════════════════════
     Search dropdown kecil di topbar
     ══════════════════════════════════════════════════════════════ */
  const topbarSearch = document.getElementById('topbarSearch');
  const searchDropdown = document.getElementById('searchDropdown');
  if (topbarSearch) {
    topbarSearch.addEventListener('focus', () => searchDropdown.classList.remove('d-none'));
    document.addEventListener('click', (e) => {
      if (!document.getElementById('searchWrapper')?.contains(e.target)) searchDropdown.classList.add('d-none');
    });
  }
</script>

{{-- ══════════ Modal konfirmasi ══════════
     Menggantikan confirm() bawaan browser -- form cukup diberi atribut
     data-confirm, opsional data-confirm-title & data-confirm-style
     (danger/warn/info). Dipakai luas di seluruh halaman admin untuk
     aksi hapus/berbahaya, jadi WAJIB ada di setiap layout. --}}
<div class="modal" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 overflow-hidden">
      <div class="p-4">
        <div class="d-flex align-items-start gap-3">
          <span id="confirmIcon" class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 bg-danger bg-opacity-10 text-danger" style="width:44px;height:44px">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </span>
          <div class="flex-grow-1 min-w-0">
            <h3 id="confirmTitle" class="h6 fw-bold text-dark mb-1">Konfirmasi</h3>
            <p id="confirmText" class="small text-muted mb-0" style="line-height:1.6"></p>
          </div>
        </div>
      </div>
      <div class="px-4 py-3 bg-light border-top d-flex align-items-center justify-content-end gap-2">
        <button type="button" id="confirmCancel" class="btn btn-outline-secondary">Batal</button>
        <button type="button" id="confirmOk" class="btn btn-danger">Lanjutkan</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const modal  = document.getElementById('confirmModal');
    const icon   = document.getElementById('confirmIcon');
    const title  = document.getElementById('confirmTitle');
    const text   = document.getElementById('confirmText');
    const okBtn  = document.getElementById('confirmOk');
    const noBtn  = document.getElementById('confirmCancel');

    let pendingForm = null;
    let pendingResolve = null;

    const styles = {
      danger: { cls: 'bg-danger bg-opacity-10 text-danger', icon: 'fa-triangle-exclamation', btn: 'btn btn-danger',  label: 'Ya, Hapus' },
      warn:   { cls: 'bg-warning bg-opacity-10 text-warning', icon: 'fa-circle-exclamation', btn: 'btn btn-primary', label: 'Lanjutkan' },
      info:   { cls: 'bg-primary bg-opacity-10 text-primary', icon: 'fa-circle-info',        btn: 'btn btn-primary', label: 'Lanjutkan' },
    };

    let confirmBackdrop = null;

    function openModal() {
      modal.classList.add('show');
      modal.style.display = 'block';
      document.body.classList.add('modal-open');

      confirmBackdrop = document.createElement('div');
      confirmBackdrop.className = 'modal-backdrop';
      document.body.appendChild(confirmBackdrop);
      requestAnimationFrame(() => confirmBackdrop.classList.add('show'));

      okBtn.focus();
    }
    function closeModal() {
      modal.classList.remove('show');
      modal.style.display = 'none';
      document.body.classList.remove('modal-open');

      if (confirmBackdrop) {
        confirmBackdrop.classList.remove('show');
        confirmBackdrop.remove();
        confirmBackdrop = null;
      }

      pendingForm = null;
      if (pendingResolve) {
        const resolve = pendingResolve;
        pendingResolve = null;
        resolve(false);
      }
    }

    function open(form) {
      pendingForm = form;
      const style = styles[form.dataset.confirmStyle || 'danger'] || styles.danger;

      icon.className = 'rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ' + style.cls;
      icon.style.width = '44px'; icon.style.height = '44px';
      icon.innerHTML = '<i class="fa-solid ' + style.icon + '"></i>';
      okBtn.className = style.btn;
      okBtn.textContent = form.dataset.confirmLabel || style.label;

      title.textContent = form.dataset.confirmTitle || 'Konfirmasi';
      text.textContent  = form.dataset.confirm;

      openModal();
    }

    /**
     * Versi promise untuk kode JS lain (mis. AJAX):
     *   if (await confirmDialog({ message: '...', style: 'warn' })) { ... }
     */
    window.confirmDialog = function (options) {
      return new Promise(function (resolve) {
        const opts = options || {};
        const style = styles[opts.style || 'info'] || styles.info;

        pendingForm = null;
        pendingResolve = resolve;

        icon.className = 'rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ' + style.cls;
        icon.style.width = '44px'; icon.style.height = '44px';
        icon.innerHTML = '<i class="fa-solid ' + style.icon + '"></i>';
        okBtn.className = style.btn;
        okBtn.textContent = opts.label || style.label;

        title.textContent = opts.title || 'Konfirmasi';
        text.textContent  = opts.message || '';

        openModal();
      });
    };

    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (form.dataset && form.dataset.confirm && !form.dataset.confirmed) {
        e.preventDefault();
        open(form);
      }
    });

    okBtn.addEventListener('click', function () {
      if (pendingResolve) {
        const resolve = pendingResolve;
        pendingResolve = null;
        closeModal();
        resolve(true);
        return;
      }
      if (!pendingForm) return;
      pendingForm.dataset.confirmed = '1';
      pendingForm.submit();
      closeModal();
    });

    noBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('show')) closeModal(); });
  })();
</script>

</body>
</html>
