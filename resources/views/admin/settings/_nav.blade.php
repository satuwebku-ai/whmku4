@php
  $settingTabs = [
    ['label' => 'Umum', 'route' => 'admin.settings.general'],
    ['label' => 'Halaman Depan', 'route' => 'admin.settings.homepage'],
    ['label' => 'Persyaratan', 'route' => 'admin.settings.requirements.index'],
    ['label' => 'PDF Invoice', 'route' => 'admin.settings.pdf-invoice'],
    ['label' => 'SEO', 'route' => 'admin.settings.seo'],
    ['label' => 'Analytics', 'route' => 'admin.settings.analytics'],
    ['label' => 'Notifikasi', 'route' => 'admin.settings.notifications'],
    ['label' => 'Keamanan', 'route' => 'admin.settings.security'],
    ['label' => 'Live Chat', 'route' => 'admin.settings.livechat'],
    ['label' => 'Trafik AI', 'route' => 'admin.ai-usage.index'],
    ['label' => 'cPanel Aplikasi', 'route' => 'admin.self-cpanel.edit'],
    ['label' => 'Cron Jobs', 'route' => 'admin.cron.index'],
  ];
@endphp

<div class="d-flex align-items-center gap-1 mb-4 border-bottom flex-wrap">
  @foreach ($settingTabs as $tab)
    <a href="{{ route($tab['route']) }}"
       class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ request()->routeIs(str_replace('.bootstrap-preview', '', $tab['route'])) ? 'border-primary text-accent' : 'border-transparent text-muted' }}">
      {{ $tab['label'] }}
    </a>
  @endforeach
</div>
