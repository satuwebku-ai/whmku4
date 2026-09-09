@php $indent = $indent ?? false; @endphp

<div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom {{ $menu->is_active ? '' : 'opacity-50' }}" style="{{ $indent ? 'padding-left:3rem;background:#f8fafc' : '' }}">

  {{-- Naik / turun urutan --}}
  <div class="d-flex flex-column flex-shrink-0" style="gap:2px">
    <form method="POST" action="{{ route('admin.nav-menu.move', $menu) }}">
      @csrf
      <input type="hidden" name="direction" value="up">
      <button type="submit" class="btn btn-link p-0 text-muted d-flex align-items-center justify-content-center" style="width:24px;height:20px" title="Naikkan">
        <i class="fa-solid fa-chevron-up" style="font-size:10px"></i>
      </button>
    </form>
    <form method="POST" action="{{ route('admin.nav-menu.move', $menu) }}">
      @csrf
      <input type="hidden" name="direction" value="down">
      <button type="submit" class="btn btn-link p-0 text-muted d-flex align-items-center justify-content-center" style="width:24px;height:20px" title="Turunkan">
        <i class="fa-solid fa-chevron-down" style="font-size:10px"></i>
      </button>
    </form>
  </div>

  <div class="flex-grow-1 min-w-0">
    <p class="small fw-medium text-dark mb-0">
      @if ($indent) <i class="fa-solid fa-arrow-turn-up fa-rotate-90 text-muted me-1" style="font-size:11px"></i> @endif
      {{ $menu->label }}
    </p>
    <p class="text-muted text-truncate mb-0" style="font-size:12px">
      @switch($menu->type)
        @case('route')
          <i class="fa-solid fa-house" style="font-size:10px"></i> Halaman bawaan — {{ \App\Models\NavMenu::BUILTIN_ROUTES[$menu->route_name] ?? $menu->route_name }}
          @break
        @case('page')
          <i class="fa-regular fa-file" style="font-size:10px"></i> Halaman — {{ $menu->page->title ?? '(halaman terhapus)' }}
          @break
        @default
          <i class="fa-solid fa-link" style="font-size:10px"></i> {{ $menu->url }}
      @endswitch
      @if ($menu->open_in_new_tab)
        <span class="text-muted">· tab baru</span>
      @endif
    </p>
  </div>

  @if (! $menu->resolved_url)
    <span class="badge badge-soft-danger flex-shrink-0" title="Tujuannya tidak lagi ada / belum terbit">Tautan rusak</span>
  @endif

  <div class="d-flex align-items-center gap-2 flex-shrink-0">
    @unless ($indent)
      <a href="{{ route('admin.nav-menu.add.page', ['parent_id' => $menu->id]) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Tambah Submenu">
        <i class="fa-solid fa-plus" style="font-size:12px"></i>
      </a>
    @endunless
    <form method="POST" action="{{ route('admin.nav-menu.status') }}">
      @csrf
      <input type="hidden" name="nav_menu_id" value="{{ $menu->id }}">
      <button type="submit" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0"
              title="{{ $menu->is_active ? 'Sembunyikan' : 'Tampilkan' }}">
        <i class="fa-solid {{ $menu->is_active ? 'fa-eye' : 'fa-eye-slash' }}" style="font-size:12px"></i>
      </button>
    </form>
    <a href="{{ route('admin.nav-menu.edit.page', $menu) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0">
      <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
    </a>
    <form method="POST" action="{{ route('admin.nav-menu.delete', $menu) }}"
          data-confirm="Hapus menu &quot;{{ $menu->label }}&quot;?{{ ! $indent && $menu->children->isNotEmpty() ? ' Submenunya ikut terhapus.' : '' }}" data-confirm-title="Hapus Menu" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
      @csrf @method('DELETE')
      <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0">
        <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
      </button>
    </form>
  </div>
</div>
