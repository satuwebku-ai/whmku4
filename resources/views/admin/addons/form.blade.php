@extends('layouts.admin')

@section('title', $addon->exists ? 'Edit Addon' : 'Tambah Addon')

@section('content')

  <a href="{{ route('admin.addons.index') }}" class="text-decoration-none text-muted" style="font-size:12px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Addons</a>
  <h1 class="h4 fw-bold text-dark mt-1 mb-4">{{ $addon->exists ? 'Edit Addon' : 'Tambah Addon' }}</h1>

  <form method="POST" action="{{ $addon->exists ? route('admin.addons.update', $addon) : route('admin.addons.store') }}" style="max-width:42rem">
    @csrf
    @if ($addon->exists) @method('PUT') @endif

    <div class="card border rounded-4 p-4 mb-3">
      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Nama Addon</label>
        <input type="text" name="name" id="nameInput" value="{{ old('name', $addon->name) }}" class="form-control form-control-sm" placeholder="mis. IP Dedicated" required>
        @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="mb-3">
        <label class="form-label small fw-medium text-dark">Slug <span class="text-muted fw-normal">(opsional, otomatis dari nama kalau kosong)</span></label>
        <input type="text" name="slug" id="slugInput" value="{{ old('slug', $addon->slug) }}" class="form-control form-control-sm" placeholder="ip-dedicated">
        @error('slug') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="form-label small fw-medium text-dark">Deskripsi</label>
        <textarea name="description" rows="3" class="form-control form-control-sm" placeholder="Dijelaskan singkat ke klien saat memilih addon ini.">{{ old('description', $addon->description) }}</textarea>
        @error('description') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <label class="form-label small fw-medium text-dark mb-3">Harga per Siklus <span class="text-muted fw-normal">(kosongkan kalau tidak ditawarkan untuk siklus itu)</span></label>
      <div class="row g-3">
        <div class="col-sm-6">
          <label class="text-muted mb-1 d-block" style="font-size:11px">Bulanan</label>
          <input type="number" step="1" min="0" name="price_monthly" value="{{ old('price_monthly', $addon->price_monthly) }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6">
          <label class="text-muted mb-1 d-block" style="font-size:11px">3 Bulan</label>
          <input type="number" step="1" min="0" name="price_quarterly" value="{{ old('price_quarterly', $addon->price_quarterly) }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6">
          <label class="text-muted mb-1 d-block" style="font-size:11px">6 Bulan</label>
          <input type="number" step="1" min="0" name="price_semi_annually" value="{{ old('price_semi_annually', $addon->price_semi_annually) }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6">
          <label class="text-muted mb-1 d-block" style="font-size:11px">Tahunan</label>
          <input type="number" step="1" min="0" name="price_annually" value="{{ old('price_annually', $addon->price_annually) }}" class="form-control form-control-sm">
        </div>
      </div>
      <p class="text-muted mt-3 mb-0" style="font-size:11px">
        Addon otomatis ikut ditagih di invoice perpanjangan layanan hosting yang memasangnya — mengikuti
        siklus tagihan layanan itu sendiri. Kalau siklus layanan tidak punya harga di sini, addon tidak
        bisa dipasang untuk layanan dengan siklus itu.
      </p>
    </div>

    <div class="card border rounded-4 p-4 mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-sm-6">
          <label class="form-label small fw-medium text-dark">Urutan Tampil</label>
          <input type="number" name="sort_order" value="{{ old('sort_order', $addon->sort_order ?? 0) }}" min="0" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6">
          <label class="d-flex align-items-center gap-2 small text-dark mb-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $addon->is_active ?? true)) class="form-check-input" style="margin-top:0">
            Aktif (bisa dipasang klien)
          </label>
        </div>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2">
      <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
      <a href="{{ route('admin.addons.index') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
    </div>
  </form>

  <script>
    (function () {
      const name = document.getElementById('nameInput');
      const slug = document.getElementById('slugInput');

      const slugify = (s) => s.toLowerCase().trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');

      let slugTouched = slug.value.length > 0;
      slug.addEventListener('input', () => { slugTouched = true; });
      name.addEventListener('input', () => {
        if (!slugTouched) slug.value = slugify(name.value);
      });
    })();
  </script>

@endsection
