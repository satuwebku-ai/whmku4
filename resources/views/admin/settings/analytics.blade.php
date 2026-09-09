@extends('layouts.admin')
@section('title', 'Analytics')
@section('content')
  @include('admin.settings._nav')

  @php use App\Models\Setting; @endphp

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Analytics</h1>
    <p class="small text-muted mb-0">Pelacakan pengunjung di halaman publik.</p>
  </div>

  <div class="rounded-3 px-3 py-2 mb-3" style="max-width:42rem;background:#eef2ff;border:1px solid #c7d2fe;font-size:12px;color:#4338ca">
    <i class="fa-solid fa-circle-info"></i>
    Isi <b>ID-nya saja</b>, bukan seluruh kode script. Script-nya dibangun otomatis oleh sistem —
    ini disengaja, karena menempelkan HTML mentah dari database ke halaman publik membuka celah XSS.
  </div>

  <form method="POST" action="{{ route('admin.settings.analytics.update') }}" class="card border rounded-4 p-4" style="max-width:42rem">
    @csrf

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Google Analytics 4 — Measurement ID</label>
      <input type="text" name="ga_measurement_id" value="{{ old('ga_measurement_id', Setting::get('ga_measurement_id')) }}" class="form-control form-control-sm" placeholder="G-XXXXXXXXXX">
      @error('ga_measurement_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Dari Google Analytics » Admin » Data Streams.</p>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Google Tag Manager — Container ID</label>
      <input type="text" name="gtm_container_id" value="{{ old('gtm_container_id', Setting::get('gtm_container_id')) }}" class="form-control form-control-sm" placeholder="GTM-XXXXXXX">
      @error('gtm_container_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Facebook Pixel ID</label>
      <input type="text" name="fb_pixel_id" value="{{ old('fb_pixel_id', Setting::get('fb_pixel_id')) }}" class="form-control form-control-sm" placeholder="1234567890123456">
      @error('fb_pixel_id') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan Pengaturan</button>
  </form>
@endsection
