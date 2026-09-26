@extends('layouts.admin')

@section('title', $menu->exists ? 'Edit Menu Utama' : 'Tambah Menu Utama')

@section('content')
  @include('admin.pages._nav')

  <div class="mb-3">
    <a href="{{ route('admin.nav-menus') }}" class="text-decoration-none text-muted" style="font-size:12px">
      <i class="fa-solid fa-arrow-left"></i> Kembali ke Menu Utama
    </a>
  </div>

  <div class="mb-3">
    <h1 class="h4 fw-bold text-dark mb-1">{{ $menu->exists ? 'Edit Menu Utama' : 'Tambah Menu Utama' }}</h1>
    <p class="small text-muted mb-0">Menu ini akan tampil langsung di navbar publik. Submenu dibuat di halaman terpisah.</p>
  </div>

  <form method="POST" action="{{ $menu->exists ? route('admin.nav-menu.update', $menu) : route('admin.nav-menu.add') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Nama Menu Utama</label>
      <input type="text" name="label" value="{{ old('label', $menu->label) }}" class="form-control form-control-sm" placeholder="Contoh: Hosting" maxlength="50" required autofocus>
      @error('label') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    @include('admin.nav-menus._destination-fields')

    <label class="d-flex align-items-center gap-2 small text-dark mb-3">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $menu->is_active ?? true)) class="form-check-input" style="margin-top:0">
      Tampilkan di navbar publik
    </label>

    <div class="d-flex align-items-center gap-2 pt-2 border-top">
      <button type="submit" class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check"></i> Simpan Menu Utama</button>
      <a href="{{ route('admin.nav-menus') }}" class="btn btn-outline-secondary btn-sm mt-2">Batal</a>
    </div>
  </form>
@endsection
