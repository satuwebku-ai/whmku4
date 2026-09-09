@extends('layouts.admin')

@section('title', $category->exists ? 'Edit Kategori' : 'Tambah Kategori')

@section('content')

  <h1 class="h4 fw-bold text-dark mb-4">{{ $category->exists ? 'Edit Kategori' : 'Tambah Kategori Produk' }}</h1>

  <form method="POST" action="{{ $category->exists ? route('admin.product-categories.update', $category) : route('admin.product-categories.store') }}" class="card border rounded-4 p-4" style="max-width:36rem">
    @csrf
    @if ($category->exists) @method('PUT') @endif

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Nama Kategori</label>
      <input type="text" name="name" id="nameInput" value="{{ old('name', $category->name) }}" class="form-control form-control-sm" required placeholder="Shared Hosting">
      @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Slug URL</label>
      <input type="text" name="slug" id="slugInput" value="{{ old('slug', $category->slug) }}" class="form-control form-control-sm" placeholder="otomatis dari nama">
      @error('slug') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Jenis Produk di Kategori Ini</label>
      <select name="type" class="form-select form-select-sm">
        <option value="hosting" @selected(old('type', $category->type ?? 'hosting') === 'hosting')>Hosting (cPanel/WHM)</option>
        <option value="vps" @selected(old('type', $category->type) === 'vps')>VPS / Cloud Server</option>
      </select>
      <p class="text-muted mt-1 mb-0" style="font-size:11px">
        Menentukan isian yang muncul saat membuat produk di kategori ini, dan server mana yang boleh dipilih.
      </p>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Deskripsi Singkat</label>
      <textarea name="description" rows="2" maxlength="500" class="form-control form-control-sm" placeholder="Tampil di halaman katalog">{{ old('description', $category->description) }}</textarea>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Ikon Font Awesome <span class="text-muted fw-normal">(opsional)</span></label>
      <input type="text" name="icon" value="{{ old('icon', $category->icon) }}" class="form-control form-control-sm" placeholder="fa-server">
      <p class="text-muted mt-1 mb-0" style="font-size:11px">Nama class tanpa "fa-solid", mis. <code>fa-server</code>, <code>fa-globe</code>, <code>fa-rocket</code>.</p>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Urutan Tampil</label>
      <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="form-control form-control-sm">
    </div>

    <label class="d-flex align-items-center gap-2 small text-dark mb-3">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true)) class="form-check-input" style="margin-top:0">
      Aktif (tampil di katalog publik)
    </label>

    <div class="d-flex align-items-center gap-2 pt-2 border-top">
      <button type="submit" class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.product-categories.index') }}" class="btn btn-outline-secondary btn-sm mt-2">Batal</a>
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
