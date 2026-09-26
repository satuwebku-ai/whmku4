@extends('layouts.admin')

@section('title', 'Menu Navigasi')

@section('content')

  @include('admin.pages._nav')

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Menu Navigasi Publik</h1>
      <p class="small text-muted mb-0">Menu yang tampil di bagian atas situs publik, di samping logo.</p>
    </div>
    <a href="{{ route('admin.nav-menu.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Tambah Menu Utama
    </a>
  </div>

  {{-- Dipindah ke ATAS, sebelum daftar -- ini yang paling sering ditanya:
       "mau nambah link, pilih yang mana?". Tiap jenis langsung jadi
       tautan ke form Tambah Menu dengan jenisnya sudah kepilih, supaya
       tidak perlu klik "Tambah Menu Utama" lalu pilih ulang. --}}
  <div class="card border rounded-4 p-4 mb-3" style="background:#f8fafc">
    <h2 class="small fw-bold text-dark mb-2"><i class="fa-solid fa-circle-info text-muted" style="font-size:11px"></i> Tiga Jenis Tautan — Klik untuk Langsung Buat</h2>
    <div class="row g-2" style="font-size:12px">
      <div class="col-md-4">
        <a href="{{ route('admin.nav-menu.add.page', ['type' => 'route']) }}" class="d-block text-decoration-none rounded-3 border bg-white p-3 h-100">
          <b class="text-dark d-block mb-1"><i class="fa-solid fa-house" style="font-size:10px"></i> Halaman Bawaan</b>
          <span class="text-muted">Hosting, Domain, Domain Premium, Pengumuman, dsb — sudah ada di sistem, tinggal dipilih.</span>
        </a>
      </div>
      <div class="col-md-4">
        <a href="{{ route('admin.nav-menu.add.page', ['type' => 'page']) }}" class="d-block text-decoration-none rounded-3 border bg-white p-3 h-100">
          <b class="text-dark d-block mb-1"><i class="fa-regular fa-file" style="font-size:10px"></i> Halaman Saya</b>
          <span class="text-muted">Konten yang kamu buat sendiri di tab <a href="{{ route('admin.pages') }}">Halaman</a> (Tentang Kami, Syarat & Ketentuan, dll).</span>
        </a>
      </div>
      <div class="col-md-4">
        <a href="{{ route('admin.nav-menu.add.page', ['type' => 'url']) }}" class="d-block text-decoration-none rounded-3 border bg-white p-3 h-100">
          <b class="text-dark d-block mb-1"><i class="fa-solid fa-link" style="font-size:10px"></i> Tautan Bebas</b>
          <span class="text-muted">Alamat apa saja, termasuk ke luar situs (mis. WhatsApp, blog, media sosial).</span>
        </a>
      </div>
    </div>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    @unless ($menus->isEmpty())
      <div class="px-4 py-2 border-bottom text-muted" style="font-size:11px;background:#f8fafc">
        <i class="fa-solid fa-circle-info"></i> Klik ikon <b>+</b> di sebelah kanan sebuah menu utama untuk menambahkan submenu di bawahnya (jadi dropdown saat diklik di situs publik).
      </div>
    @endunless
    <div>
      @forelse ($menus as $menu)
        @include('admin.nav-menus._row', ['menu' => $menu, 'indent' => false])
        @foreach ($menu->allChildren as $child)
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

@endsection
