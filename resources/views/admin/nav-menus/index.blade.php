@extends('layouts.admin')

@section('title', 'Menu Utama')

@section('content')

  @include('admin.pages._nav')

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Menu Utama</h1>
      <p class="small text-muted mb-0">Atur menu yang tampil langsung di navbar situs publik.</p>
    </div>
    <a href="{{ route('admin.nav-menu.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Tambah Menu Utama
    </a>
  </div>

  <div class="card border rounded-4 p-4 mb-3" style="background:#f8fafc">
    <div class="d-flex align-items-start gap-3">
      <div class="text-primary pt-1"><i class="fa-solid fa-circle-info"></i></div>
      <div class="small text-muted">
        <div class="fw-bold text-dark mb-1">Alur navigasi CMS</div>
        <div>1. Buat <b>Menu Utama</b> di halaman ini.</div>
        <div>2. Buka <b>Submenu / Subnav</b> untuk membuat dropdown di bawah menu utama.</div>
        <div>3. Menu utama dan submenu memiliki urutan, status aktif, serta pengaturan masing-masing.</div>
      </div>
    </div>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div class="px-4 py-2 border-bottom text-muted" style="font-size:11px;background:#f8fafc">
      <i class="fa-solid fa-bars"></i> Hanya menu level pertama yang ditampilkan di sini.
      Submenu dikelola di halaman <a href="{{ route('admin.nav-submenus') }}">Submenu / Subnav</a>.
    </div>

    @forelse ($menus as $menu)
      @php $hasValidChild = $menu->children->contains(fn ($c) => (bool) $c->resolved_url); @endphp
      <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom {{ $menu->is_active ? '' : 'opacity-50' }}">
        <div class="d-flex flex-column flex-shrink-0" style="gap:2px">
          <form method="POST" action="{{ route('admin.nav-menu.move', $menu) }}">
            @csrf
            <input type="hidden" name="direction" value="up">
            <button type="submit" class="btn btn-link p-0 text-muted" style="width:24px;height:20px" title="Naikkan">
              <i class="fa-solid fa-chevron-up" style="font-size:10px"></i>
            </button>
          </form>
          <form method="POST" action="{{ route('admin.nav-menu.move', $menu) }}">
            @csrf
            <input type="hidden" name="direction" value="down">
            <button type="submit" class="btn btn-link p-0 text-muted" style="width:24px;height:20px" title="Turunkan">
              <i class="fa-solid fa-chevron-down" style="font-size:10px"></i>
            </button>
          </form>
        </div>

        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <p class="small fw-bold text-dark mb-0">{{ $menu->label }}</p>
            <span class="badge bg-light text-dark border" style="font-size:10px">
              {{ $menu->all_children_count }} submenu
            </span>
          </div>
          <p class="text-muted text-truncate mb-0" style="font-size:12px">
            @if ($menu->default_child_id)
              <i class="fa-solid fa-arrow-turn-up fa-rotate-90" style="font-size:10px"></i>
              Langsung ke Subnav — {{ $menu->defaultChild->label ?? '(subnav terhapus)' }}
            @else
              @switch($menu->type)
                @case('route')
                  <i class="fa-solid fa-house" style="font-size:10px"></i>
                  Halaman bawaan — {{ \App\Models\NavMenu::BUILTIN_ROUTES[$menu->route_name] ?? $menu->route_name }}
                  @break
                @case('page')
                  <i class="fa-regular fa-file" style="font-size:10px"></i>
                  Halaman — {{ $menu->page->title ?? '(halaman terhapus)' }}
                  @break
                @default
                  <i class="fa-solid fa-link" style="font-size:10px"></i> {{ $menu->url }}
              @endswitch
            @endif
          </p>
        </div>

        @if (! $menu->resolved_url && ! $hasValidChild)
          <span class="badge badge-soft-danger flex-shrink-0">Tautan rusak</span>
        @endif

        <div class="d-flex align-items-center gap-2 flex-shrink-0">
          <a href="{{ route('admin.nav-submenus', ['parent' => $menu->id]) }}" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
            <i class="fa-solid fa-list" style="font-size:11px"></i>
            <span class="d-none d-md-inline">Subnav</span>
          </a>
          <form method="POST" action="{{ route('admin.nav-menu.status') }}">
            @csrf
            <input type="hidden" name="nav_menu_id" value="{{ $menu->id }}">
            <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0" title="{{ $menu->is_active ? 'Sembunyikan' : 'Tampilkan' }}">
              <i class="fa-solid {{ $menu->is_active ? 'fa-eye' : 'fa-eye-slash' }}" style="font-size:12px"></i>
            </button>
          </form>
          <a href="{{ route('admin.nav-menu.edit.page', $menu) }}" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0">
            <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
          </a>
          <form method="POST" action="{{ route('admin.nav-menu.delete', $menu) }}"
                data-confirm="Hapus menu utama &quot;{{ $menu->label }}&quot;? Semua submenu di bawahnya ikut terhapus."
                data-confirm-title="Hapus Menu Utama" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm" style="width:32px;height:32px;padding:0">
              <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
            </button>
          </form>
        </div>
      </div>
    @empty
      <div class="text-center py-5">
        <p class="small text-dark mb-1">Belum ada menu utama.</p>
        <p class="text-muted mb-3" style="font-size:12px">Mulai dengan membuat menu utama pertama.</p>
        <a href="{{ route('admin.nav-menu.add.page') }}" class="btn btn-primary btn-sm">+ Tambah Menu Utama</a>
      </div>
    @endforelse
  </div>

@endsection