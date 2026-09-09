@extends('layouts.admin')

@section('title', $banner->exists ? 'Edit Banner Promo' : 'Tambah Banner Promo')

@section('content')

  <a href="{{ route('admin.promo-banners.index') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Banner Promo</a>
  <h1 class="h4 fw-bold text-dark mt-1 mb-4">{{ $banner->exists ? 'Edit Banner Promo' : 'Tambah Banner Promo' }}</h1>

  <form method="POST" action="{{ $banner->exists ? route('admin.promo-banners.update', $banner) : route('admin.promo-banners.store') }}"
        enctype="multipart/form-data" style="max-width:42rem">
    @csrf
    @if ($banner->exists) @method('PUT') @endif

    <div class="card border rounded-4 p-4 mb-3">
      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Judul (opsional)</label>
        <input type="text" name="title" value="{{ old('title', $banner->title) }}" class="form-control form-control-sm" placeholder="Promo Hosting Diskon 30%">
        @error('title') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">Kosongkan kalau gambar bannernya sudah punya judul sendiri di dalam gambar -- teks judul di sini akan ditampilkan sebagai overlay di atas gambar.</p>
      </div>
      <div>
        <label class="form-label small fw-medium text-dark">Subjudul (opsional)</label>
        <input type="text" name="subtitle" value="{{ old('subtitle', $banner->subtitle) }}" class="form-control form-control-sm" placeholder="Berlaku sampai akhir bulan ini">
        @error('subtitle') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <label class="form-label small fw-medium text-dark">Gambar Banner</label>
      @if ($banner->image)
        <img src="{{ route('banner.file', $banner->image) }}" alt="{{ $banner->title }}" class="w-100 rounded-3 border mb-3" style="max-width:28rem">
      @endif
      <input type="file" name="image" accept="image/*" class="form-control form-control-sm">
      @error('image') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Maksimal 2 MB. Gambar ditampilkan apa adanya sesuai rasio aslinya di halaman publik (tidak dipotong) -- disarankan rasio lebar seperti 16:5 atau 3:1 supaya pas dengan tampilan carousel.</p>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Tautan Tujuan (opsional)</label>
        <input type="text" name="link_url" value="{{ old('link_url', $banner->link_url) }}" class="form-control form-control-sm" placeholder="/hosting atau https://...">
        @error('link_url') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Teks Tombol (opsional)</label>
        <input type="text" name="button_text" value="{{ old('button_text', $banner->button_text) }}" class="form-control form-control-sm" placeholder="Lihat Paket">
        @error('button_text') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <label class="d-flex align-items-center gap-2 small text-dark mb-0">
        <input type="checkbox" name="open_in_new_tab" value="1" @checked(old('open_in_new_tab', $banner->open_in_new_tab)) class="form-check-input" style="margin-top:0">
        Buka di tab baru
      </label>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <label class="form-label small fw-medium text-dark mb-2">Jadwal Tayang (opsional — kosongkan supaya tayang terus)</label>
      <div class="row g-3">
        <div class="col-sm-6">
          <label class="text-muted mb-1 d-block" style="font-size:11px">Mulai</label>
          <input type="date" name="starts_at" value="{{ old('starts_at', $banner->starts_at?->format('Y-m-d')) }}" class="form-control form-control-sm">
          @error('starts_at') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>
        <div class="col-sm-6">
          <label class="text-muted mb-1 d-block" style="font-size:11px">Sampai</label>
          <input type="date" name="ends_at" value="{{ old('ends_at', $banner->ends_at?->format('Y-m-d')) }}" class="form-control form-control-sm">
          @error('ends_at') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>
      </div>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <label class="form-label small fw-medium text-dark">Tampil di Halaman</label>
      <select name="display_page" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem;max-width:16rem">
        @foreach (\App\Models\PromoBanner::PAGES as $key => $label)
          <option value="{{ $key }}" @selected(old('display_page', $banner->display_page ?? 'all') === $key)>{{ $label }}</option>
        @endforeach
      </select>
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Banner cuma tampil di halaman yang dipilih (atau semua halaman <em>web</em> kalau "Semua Halaman" dipilih). Khusus "Email Transaksional" dan "PDF Invoice" harus dipilih sendiri -- tidak otomatis ikut kalau "Semua Halaman" yang dipilih, supaya banner buat website tidak tiba-tiba nongol di invoice/email.</p>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <label class="d-flex align-items-center gap-2 small text-dark mb-0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner->is_active ?? true)) class="form-check-input" style="margin-top:0">
        Aktif (tampil di situs publik)
      </label>
    </div>

    <div class="d-flex align-items-center gap-2">
      <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
      <a href="{{ route('admin.promo-banners.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>

@endsection
