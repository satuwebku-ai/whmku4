@php
  $adminTabs = [
    ['label' => 'Daftar Admin', 'route' => 'admin.admins'],
    ['label' => 'Percobaan Login', 'route' => 'admin.login-attempts'],
  ];
@endphp

<div class="d-flex align-items-center gap-1 mb-4 border-bottom flex-wrap">
  @foreach ($adminTabs as $tab)
    <a href="{{ route($tab['route']) }}"
       class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ request()->routeIs(str_replace('.bootstrap-preview', '', $tab['route'])) ? 'border-primary text-accent' : 'border-transparent text-muted' }}">
      {{ $tab['label'] }}
    </a>
  @endforeach
</div>
