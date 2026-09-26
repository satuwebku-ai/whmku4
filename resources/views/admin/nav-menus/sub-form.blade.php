@extends('layouts.admin')

@section('title', $menu->exists ? 'Edit Submenu' : 'Tambah Submenu')

@section('content')
  @include('admin.pages._nav')

  <div class="mb-3">
    <a href="{{ route('admin.nav-submenus') }}" class="text-decoration-none text-muted" style="font-size:12px">
      <i class="fa-solid fa-arrow-left"></i> Kembali ke Submenu / Subnav
    </a>
  </div>

  <div class="mb-3">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $menu->exists ? 'Edit Submenu' : 'Tambah Submenu' }}</h1>
    <p class="small text-muted mb-0">Submenu akan tampil sebagai dropdown di bawah satu Menu Utama.</p>
  </div>

  <form method="POST" action="{{ $menu->exists ? route('admin.nav-submenu.update', $menu) : route('admin.nav-submenu.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Menu Utama</label>
      <select name="parent_id" class="form-select form-select-sm" required>
        <option value="">— Pilih Menu Utama —</option>
        @foreach ($parentOptions as $parent)
          <option value="{{ $parent->id }}" @selected((int) old('parent_id', $menu->parent_id) === $parent->id)>{{ $parent->label }}</option>
        @endforeach
      </select>
      @error('parent_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Pilih tepat satu Menu Utama. Submenu tidak dapat memiliki submenu lagi.</p>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Nama Submenu</label>
      <input type="text" name="label" value="{{ old('label', $menu->label) }}" class="form-control form-control-sm" placeholder="Contoh: Shared Hosting" maxlength="50" required autofocus>
      @error('label') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    @include('admin.nav-menus._destination-fields')

    <label class="d-flex align-items-center gap-2 small text-dark mb-3">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $menu->is_active ?? true)) class="form-check-input" style="margin-top:0">
      Tampilkan di dropdown
    </label>

    <div class="d-flex align-items-center gap-2 pt-2 border-top">
      <button type="submit" class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check"></i> Simpan Submenu</button>
      <a href="{{ route('admin.nav-submenus') }}" class="btn btn-outline-secondary btn-sm mt-2">Batal</a>
    </div>
  </form>
@endsection
