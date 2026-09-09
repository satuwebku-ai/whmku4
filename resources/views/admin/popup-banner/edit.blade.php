@extends('layouts.admin')
@section('title', 'Banner Popup')
@section('content')

  @include('admin.pages._nav')

  @php use App\Models\Setting; @endphp

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Banner Popup</h1>
    <p class="small text-muted mb-0">
      Muncul sebagai jendela di atas halaman depan (Beranda) begitu pengunjung membuka situs — beda dari Banner Promo yang tampil sebagai carousel di dalam halaman.
    </p>
  </div>

  <form method="POST" action="{{ route('admin.popup-banner.update') }}" enctype="multipart/form-data" style="max-width:42rem">
    @csrf

    <div class="card border rounded-4 p-4 mb-3">
      <label class="d-flex align-items-center gap-2 small fw-medium text-dark mb-1" style="cursor:pointer;width:fit-content">
        <input type="checkbox" name="popup_banner_enabled" value="1" @checked(Setting::get('popup_banner_enabled', '0') === '1')
               class="form-check-input" style="margin-top:0">
        Aktifkan banner popup
      </label>
      <p class="text-muted mb-0" style="font-size:11px">Kalau dimatikan, tidak ada popup yang muncul di Beranda sama sekali.</p>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Gambar</label>
        @php $currentImage = Setting::get('popup_banner_image'); @endphp
        @if ($currentImage)
          <div class="mb-2 d-flex align-items-center gap-3">
            <img src="{{ route('branding.file', $currentImage) }}" alt="Banner popup" class="rounded-3 border" style="height:80px;object-fit:cover">
            <label class="d-flex align-items-center gap-2 text-danger" style="font-size:12px;cursor:pointer">
              <input type="checkbox" name="remove_popup_banner_image" value="1" class="form-check-input" style="margin-top:0">
              Hapus gambar ini
            </label>
          </div>
        @endif
        <input type="file" name="popup_banner_image" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm">
        @error('popup_banner_image') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        <p class="text-muted mt-1 mb-0" style="font-size:11px">PNG/JPG/WEBP, maksimal 2 MB. Gambar ditampilkan apa adanya sesuai rasio aslinya (tidak dipotong), dibatasi tinggi maksimum supaya popup tidak terlalu besar di layar.</p>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Judul</label>
        <input type="text" name="popup_banner_title" value="{{ old('popup_banner_title', Setting::get('popup_banner_title')) }}" class="form-control form-control-sm" placeholder="Promo Spesial Bulan Ini!">
        @error('popup_banner_title') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>

      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Deskripsi</label>
        <textarea name="popup_banner_description" rows="3" class="form-control form-control-sm" placeholder="Diskon 20% untuk semua paket hosting, berlaku sampai akhir bulan.">{{ old('popup_banner_description', Setting::get('popup_banner_description')) }}</textarea>
        @error('popup_banner_description') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>

      <div class="row g-3">
        <div class="col-sm-6">
          <label class="form-label small fw-medium text-dark">Teks Tombol</label>
          <input type="text" name="popup_banner_button_text" value="{{ old('popup_banner_button_text', Setting::get('popup_banner_button_text', 'Lihat Sekarang')) }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6">
          <label class="form-label small fw-medium text-dark">Tautan Tombol</label>
          <input type="text" name="popup_banner_link_url" value="{{ old('popup_banner_link_url', Setting::get('popup_banner_link_url')) }}" class="form-control form-control-sm" placeholder="/hosting atau https://...">
          @error('popup_banner_link_url') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
        </div>
      </div>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <label class="form-label small fw-medium text-dark">Seberapa Sering Muncul</label>
      @php $freq = old('popup_banner_frequency', Setting::get('popup_banner_frequency', 'once_per_day')); @endphp
      <select name="popup_banner_frequency" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem;max-width:16rem">
        <option value="every_visit" @selected($freq === 'every_visit')>Setiap kali buka halaman</option>
        <option value="once_per_session" @selected($freq === 'once_per_session')>Sekali per kunjungan (sampai browser ditutup)</option>
        <option value="once_per_day" @selected($freq === 'once_per_day')>Sekali per hari per pengunjung</option>
      </select>
      <p class="text-muted mt-1 mb-0" style="font-size:11px">
        "Setiap kali buka halaman" cukup mengganggu untuk pengunjung yang sering balik — cuma disarankan untuk pengumuman yang benar-benar penting/mendesak.
      </p>
    </div>

    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan Pengaturan</button>
  </form>

@endsection
