@php
  $domainModuleTabs = [
    ['label' => 'Domain Aktif', 'route' => 'admin.domains'],
    ['label' => 'Cek Domain', 'route' => 'admin.domain.search'],
    ['label' => 'TLD Pricing', 'route' => 'admin.tlds.pricing'],
    ['label' => 'Status TLD', 'route' => 'admin.tlds.index'],
    ['label' => 'ID Protection', 'route' => 'admin.tlds.privacy'],
    ['label' => 'Registrar', 'route' => 'admin.registrars.index'],
  ];
@endphp

<div class="d-flex align-items-center gap-1 mb-4 border-bottom flex-wrap">
  @foreach ($domainModuleTabs as $tab)
    <a href="{{ route($tab['route']) }}"
       class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 {{ request()->routeIs(str_replace('.bootstrap-preview', '', $tab['route'])) || ($tab['label'] === 'Domain Aktif' && request()->routeIs('admin.domains*')) ? 'border-primary text-accent' : 'border-transparent text-muted' }}">
      {{ $tab['label'] }}
    </a>
  @endforeach
</div>
