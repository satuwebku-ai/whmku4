@php
  // Tab Pengaturan dikelompokkan menurut fungsi. "Halaman Depan" sudah
  // pindah ke menu Situs Publik (CMS) karena itu konten situs, bukan
  // konfigurasi sistem.
  $settingGroups = [
    'Situs' => [
      ['label' => 'Umum', 'route' => 'admin.settings.general', 'match' => ['admin.settings.general*']],
      ['label' => 'SEO', 'route' => 'admin.settings.seo', 'match' => ['admin.settings.seo*']],
      ['label' => 'Analytics', 'route' => 'admin.settings.analytics', 'match' => ['admin.settings.analytics*']],
    ],
    'Bisnis' => [
      ['label' => 'Persyaratan Berkas', 'route' => 'admin.settings.requirements.index', 'match' => ['admin.settings.requirements.*']],
      ['label' => 'PDF Invoice', 'route' => 'admin.settings.pdf-invoice', 'match' => ['admin.settings.pdf-invoice*']],
      ['label' => 'Affiliate', 'route' => 'admin.settings.affiliate', 'match' => ['admin.settings.affiliate*']],
    ],
    'Komunikasi' => [
      ['label' => 'Notifikasi', 'route' => 'admin.settings.notifications', 'match' => ['admin.settings.notifications*']],
      ['label' => 'Email', 'route' => 'admin.settings.email', 'match' => ['admin.settings.email*']],
      ['label' => 'Live Chat', 'route' => 'admin.settings.livechat', 'match' => ['admin.settings.livechat*']],
    ],
    'Sistem' => [
      ['label' => 'Keamanan', 'route' => 'admin.settings.security', 'match' => ['admin.settings.security*']],
      ['label' => 'Tampilan Notifikasi', 'route' => 'admin.settings.toast.edit', 'match' => ['admin.settings.toast.*']],
      ['label' => 'Trafik AI', 'route' => 'admin.ai-usage.index', 'match' => ['admin.ai-usage.*']],
      ['label' => 'cPanel Aplikasi', 'route' => 'admin.self-cpanel.edit', 'match' => ['admin.self-cpanel.*']],
      ['label' => 'Cron Jobs', 'route' => 'admin.cron.index', 'match' => ['admin.cron.*']],
    ],
  ];
@endphp

<div class="d-flex align-items-end gap-4 mb-4 border-bottom flex-wrap">
  @foreach ($settingGroups as $groupLabel => $tabs)
    <div>
      <div class="text-uppercase text-muted fw-semibold px-3" style="font-size:10px;letter-spacing:.12em">{{ $groupLabel }}</div>
      <div class="d-flex align-items-center gap-1">
        @foreach ($tabs as $tab)
          <a href="{{ route($tab['route']) }}"
             class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ request()->routeIs(...$tab['match']) ? 'border-primary text-accent' : 'border-transparent text-muted' }}">
            {{ $tab['label'] }}
          </a>
        @endforeach
      </div>
    </div>
  @endforeach
</div>
