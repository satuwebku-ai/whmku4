@php
  /*
   * Navigasi CMS: satu baris tab untuk semua bagian konten situs, plus
   * baris sub-tab bila bagian itu punya lebih dari satu halaman. Dipakai
   * semua halaman CMS lewat @include('admin.pages._nav').
   *
   * 'match' = pola route yang menandai tab aktif. Pola yang lebih spesifik
   * (mis. kategori blog) cukup masuk ke sub-tab; tab induknya tetap menyala.
   */
  $cmsTabs = [
    'home' => [
      'label' => 'Beranda', 'route' => 'admin.settings.homepage',
      'match' => ['admin.settings.homepage*'],
    ],
    'pages' => [
      'label' => 'Halaman', 'route' => 'admin.pages',
      'match' => ['admin.pages', 'admin.page.*'],
    ],
    'nav' => [
      'label' => 'Navigasi', 'route' => 'admin.nav-menus',
      'match' => ['admin.nav-menus', 'admin.nav-menu.*', 'admin.nav-submenus', 'admin.nav-submenu.*'],
      'subs' => [
        ['label' => 'Menu Utama', 'route' => 'admin.nav-menus', 'match' => ['admin.nav-menus', 'admin.nav-menu.*']],
        ['label' => 'Submenu', 'route' => 'admin.nav-submenus', 'match' => ['admin.nav-submenus', 'admin.nav-submenu.*']],
      ],
    ],
    'promo' => [
      'label' => 'Promosi', 'route' => 'admin.promo-banners.index',
      'match' => ['admin.promo-banners.*', 'admin.popup-banner.*', 'admin.announcements', 'admin.announcement.*'],
      'subs' => [
        ['label' => 'Banner Promo', 'route' => 'admin.promo-banners.index', 'match' => ['admin.promo-banners.*']],
        ['label' => 'Popup', 'route' => 'admin.popup-banner.edit', 'match' => ['admin.popup-banner.*']],
        ['label' => 'Pengumuman', 'route' => 'admin.announcements', 'match' => ['admin.announcements', 'admin.announcement.*']],
      ],
    ],
    'blog' => [
      'label' => 'Blog', 'route' => 'admin.blog.index',
      'match' => ['admin.blog.*'],
      'subs' => [
        ['label' => 'Artikel', 'route' => 'admin.blog.index', 'match' => ['admin.blog.*'], 'except' => ['admin.blog.categories*']],
        ['label' => 'Kategori', 'route' => 'admin.blog.categories', 'match' => ['admin.blog.categories*']],
      ],
    ],
    'kb' => [
      'label' => 'Pusat Bantuan', 'route' => 'admin.knowledge-base.index',
      'match' => ['admin.knowledge-base.*'],
      'subs' => [
        ['label' => 'Panduan', 'route' => 'admin.knowledge-base.index', 'match' => ['admin.knowledge-base.*'], 'except' => ['admin.knowledge-base.categories*']],
        ['label' => 'Kategori', 'route' => 'admin.knowledge-base.categories', 'match' => ['admin.knowledge-base.categories*']],
      ],
    ],
    'notif' => [
      'label' => 'Notifikasi', 'route' => 'admin.notification-templates.index',
      'match' => ['admin.notification-templates.*'],
    ],
  ];

  $cmsIsActive = static fn (array $item): bool => request()->routeIs(...$item['match'])
    && ! (isset($item['except']) && request()->routeIs(...$item['except']));

  $cmsActiveTab = collect($cmsTabs)->first(fn ($tab) => $cmsIsActive($tab));
@endphp

{{-- Memakai kelas .nav-tabs dan .section-jump-nav yang sudah ada di lumora-admin.css --}}
<div class="mb-4">
  <a href="{{ route('admin.cms.dashboard') }}" class="btn btn-outline-secondary btn-sm mb-3">
    <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i> Ringkasan CMS
  </a>

  <ul class="nav nav-tabs" aria-label="Bagian CMS">
    @foreach ($cmsTabs as $tab)
      <li class="nav-item">
        <a href="{{ route($tab['route']) }}"
           class="nav-link {{ $cmsActiveTab === $tab ? 'active' : '' }}"
           @if ($cmsActiveTab === $tab) aria-current="page" @endif>{{ $tab['label'] }}</a>
      </li>
    @endforeach
  </ul>

  @if ($cmsActiveTab && ! empty($cmsActiveTab['subs']))
    <ul class="nav section-jump-nav gap-1 mt-3" aria-label="Sub bagian {{ $cmsActiveTab['label'] }}">
      @foreach ($cmsActiveTab['subs'] as $sub)
        <li class="nav-item">
          <a href="{{ route($sub['route']) }}"
             class="nav-link py-1 {{ $cmsIsActive($sub) ? 'active' : '' }}">{{ $sub['label'] }}</a>
        </li>
      @endforeach
    </ul>
  @endif
</div>
