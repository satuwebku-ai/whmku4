@extends('layouts.admin')

@section('title', 'Menu Navigasi')

@section('content')

  @include('admin.pages._nav')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Menu Navigasi Publik</h1>
      <p class="small text-muted mb-0">Menu yang tampil di bagian atas situs publik, di samping logo.</p>
    </div>
    <a href="{{ route('admin.nav-menu.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Tambah Menu
    </a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div>
      @forelse ($menus as $menu)
        @include('admin.nav-menus._row', ['menu' => $menu, 'indent' => false])
        @foreach ($menu->children as $child)
          @include('admin.nav-menus._row', ['menu' => $child, 'indent' => true])
        @endforeach
      @empty
        <div class="text-center py-5">
          <p class="small text-dark mb-1">Belum ada menu.</p>
          <p class="text-muted mb-0" style="font-size:12px">Situs publik akan tampil tanpa menu navigasi sampai kamu menambahkan satu.</p>
        </div>
      @endforelse
    </div>
  </div>

  <div class="card border rounded-4 p-4 mt-3">
    <h2 class="small fw-bold text-dark mb-2">Tiga Jenis Tautan</h2>
    <div class="text-muted" style="font-size:12px">
      <div class="mb-2"><b class="text-dark">Halaman Bawaan</b> — Hosting, Domain, Pengumuman, dsb. Sudah ada di sistem, tinggal dipilih.</div>
      <div class="mb-2"><b class="text-dark">Halaman</b> — konten yang kamu buat sendiri di tab Halaman (Tentang Kami, Syarat & Ketentuan, dll).</div>
      <div><b class="text-dark">Tautan Bebas</b> — alamat apa saja, termasuk ke luar situs (mis. WhatsApp, blog, media sosial).</div>
    </div>
  </div>

@endsection
