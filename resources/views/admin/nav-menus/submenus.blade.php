@extends('layouts.admin')

@section('title', 'Submenu / Subnav')

@section('content')

  @include('admin.pages._nav')

  @php $selectedParent = request()->integer('parent'); @endphp

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Submenu / Subnav</h1>
      <p class="small text-muted mb-0">Atur item dropdown yang berada di bawah setiap Menu Utama.</p>
    </div>
    <a href="{{ route('admin.nav-submenu.add.page', $selectedParent ? ['parent_id' => $selectedParent] : []) }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Tambah Submenu
    </a>
  </div>

  <div class="card border rounded-4 p-4 mb-3" style="background:#f8fafc">
    <div class="row g-3 align-items-end">
      <div class="col-md-8">
        <label class="form-label small fw-medium mb-1">Tampilkan submenu dari Menu Utama</label>
        <select class="form-select form-select-sm" onchange="window.location=this.value">
          <option value="{{ route('admin.nav-submenus') }}">Semua Menu Utama</option>
          @foreach ($mainMenus as $main)
            <option value="{{ route('admin.nav-submenus', ['parent' => $main->id]) }}" @selected($selectedParent === $main->id)>
              {{ $main->label }} ({{ $main->all_children_count }} submenu)
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4 small text-muted">
        <i class="fa-solid fa-circle-info"></i>
        Submenu hanya boleh satu tingkat. Tidak ada submenu di dalam submenu.
      </div>
    </div>
  </div>

  @php
    $groups = $selectedParent
      ? $mainMenus->where('id', $selectedParent)
      : $mainMenus;
  @endphp

  @forelse ($groups as $main)
    <div class="card border rounded-4 overflow-hidden mb-3">
      <div class="d-flex align-items-center justify-content-between gap-2 px-4 py-3 border-bottom" style="background:#f8fafc">
        <div>
          <div class="small fw-bold text-dark"><i class="fa-solid fa-bars-staggered text-primary me-1"></i>{{ $main->label }}</div>
          <div class="text-muted" style="font-size:11px">Menu Utama</div>
        </div>
        <a href="{{ route('admin.nav-submenu.add.page', ['parent_id' => $main->id]) }}" class="btn btn-outline-primary btn-sm">
          <i class="fa-solid fa-plus"></i> Tambah Submenu
        </a>
      </div>

      @forelse ($main->allChildren as $child)
        <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom {{ $child->is_active ? '' : 'opacity-50' }}">
          <div class="d-flex flex-column flex-shrink-0" style="gap:2px">
            <form method="POST" action="{{ route('admin.nav-menu.move', $child) }}">
              @csrf
              <input type="hidden" name="direction" value="up">
              <button type="submit" class="btn btn-link p-0 text-muted" style="width:24px;height:20px" title="Naikkan">
                <i class="fa-solid fa-chevron-up" style="font-size:10px"></i>
              </button>
            </form>
            <form method="POST" action="{{ route('admin.nav-menu.move', $child) }}">
              @csrf
              <input type="hidden" name="direction" value="down">
              <button type="submit" class="btn btn-link p-0 text-muted" style="width:24px;height:20px" title="Turunkan">
                <i class="fa-solid fa-chevron-down" style="font-size:10px"></i>
              </button>
            </form>
          </div>

          <div class="flex-grow-1 min-w-0">
            <p class="small fw-medium text-dark mb-0">{{ $child->label }}</p>
            <p class="text-muted text-truncate mb-0" style="font-size:12px">
              @switch($child->type)
                @case('route')
                  <i class="fa-solid fa-house" style="font-size:10px"></i>
                  {{ \App\Models\NavMenu::BUILTIN_ROUTES[$child->route_name] ?? $child->route_name }}
                  @break
                @case('page')
                  <i class="fa-regular fa-file" style="font-size:10px"></i>
                  {{ $child->page->title ?? '(halaman terhapus)' }}
                  @break
                @default
                  <i class="fa-solid fa-link" style="font-size:10px"></i> {{ $child->url }}
              @endswitch
            </p>
          </div>

          @if (! $child->resolved_url)
            <span class="badge badge-soft-danger flex-shrink-0">Tautan rusak</span>
          @endif

          <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <form method="POST" action="{{ route('admin.nav-menu.status') }}">
              @csrf
              <input type="hidden" name="nav_menu_id" value="{{ $child->id }}">
              <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0" title="{{ $child->is_active ? 'Sembunyikan' : 'Tampilkan' }}">
                <i class="fa-solid {{ $child->is_active ? 'fa-eye' : 'fa-eye-slash' }}" style="font-size:12px"></i>
              </button>
            </form>
            <a href="{{ route('admin.nav-submenu.edit.page', $child) }}" class="btn btn-outline-secondary btn-sm" style="width:32px;height:32px;padding:0">
              <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
            </a>
            <form method="POST" action="{{ route('admin.nav-menu.delete', $child) }}"
                  data-confirm="Hapus submenu &quot;{{ $child->label }}&quot;?"
                  data-confirm-title="Hapus Submenu" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-outline-danger btn-sm" style="width:32px;height:32px;padding:0">
                <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
              </button>
            </form>
          </div>
        </div>
      @empty
        <div class="text-center py-4">
          <p class="small text-muted mb-2">Belum ada submenu untuk <b>{{ $main->label }}</b>.</p>
          <a href="{{ route('admin.nav-submenu.add.page', ['parent_id' => $main->id]) }}" class="btn btn-outline-primary btn-sm">+ Tambah Submenu</a>
        </div>
      @endforelse
    </div>
  @empty
    <div class="card border rounded-4 text-center py-5">
      <p class="small text-dark mb-1">Belum ada Menu Utama.</p>
      <p class="small text-muted mb-3">Buat Menu Utama terlebih dahulu sebelum membuat Submenu.</p>
      <a href="{{ route('admin.nav-menu.add.page') }}" class="btn btn-primary btn-sm">+ Buat Menu Utama</a>
    </div>
  @endforelse

@endsection
