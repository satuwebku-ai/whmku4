@php
  $cmsTabs = [
    ['label' => 'Halaman', 'route' => 'admin.pages'],
    ['label' => 'Pengumuman', 'route' => 'admin.announcements'],
    ['label' => 'Menu Utama', 'route' => 'admin.nav-menus'],
    ['label' => 'Submenu / Subnav', 'route' => 'admin.nav-submenus'],
    ['label' => 'Banner Promo', 'route' => 'admin.promo-banners.index'],
    ['label' => 'Banner Popup', 'route' => 'admin.popup-banner.edit'],
  ];
@endphp

<div class="d-flex align-items-center gap-1 mb-4 border-bottom flex-wrap">
  @foreach ($cmsTabs as $tab)
    <a href="{{ route($tab['route']) }}"
       class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ request()->routeIs($tab['route']) ? 'border-primary text-accent' : 'border-transparent text-muted' }}">
      {{ $tab['label'] }}
    </a>
  @endforeach
</div>
