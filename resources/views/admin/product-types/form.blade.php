@extends('layouts.admin')

@section('title', $type->exists ? 'Edit Jenis Produk' : 'Tambah Jenis Produk')

@section('content')

  <h1 class="h4 fw-bold text-dark mb-4">{{ $type->exists ? 'Edit Jenis Produk' : 'Tambah Jenis Produk' }}</h1>

  <form method="POST" action="{{ $type->exists ? route('admin.product-types.update', $type) : route('admin.product-types.store') }}" class="card border rounded-4 p-4" style="max-width:36rem" autocomplete="off">
    @csrf
    @if ($type->exists) @method('PUT') @endif

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Nama Jenis</label>
      <input type="text" name="name" value="{{ old('name', $type->name) }}" class="form-control form-control-sm" required placeholder="WordPress Hosting">
      @error('name') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Slug (opsional)</label>
      <input type="text" name="slug" value="{{ old('slug', $type->slug) }}" class="form-control form-control-sm" placeholder="otomatis dari nama">
      @error('slug') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Perilaku</label>
      <select name="kind" class="form-select form-select-sm">
        @foreach (\App\Models\ProductType::KINDS as $key => $label)
          <option value="{{ $key }}" @selected(old('kind', $type->kind) === $key)>{{ $label }}</option>
        @endforeach
      </select>
      @error('kind') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      <p class="text-muted mt-1 mb-0" style="font-size:11px">
        Menentukan server yang boleh dipilih, cara tagihan, dan awalan URL katalog untuk produk di jenis ini.
        @if ($type->exists && ($type->categories_count ?? 0) > 0)
          Perilaku terkunci karena sudah dipakai {{ $type->categories_count }} kategori.
        @endif
      </p>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-sm-7">
        <label class="form-label small fw-medium text-dark">Ikon Font Awesome <span class="text-muted fw-normal">(opsional)</span></label>
        <input type="text" name="icon" value="{{ old('icon', $type->icon) }}" class="form-control form-control-sm" placeholder="fa-server">
        @error('icon') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
      <div class="col-sm-5">
        <label class="form-label small fw-medium text-dark">Warna Tab</label>
        <input type="color" name="color" value="{{ old('color', $type->color ?? '#4f46e5') }}" class="form-control form-control-color w-100" style="height:31px">
        @error('color') <p class="text-danger mt-1 mb-0" style="font-size:12px">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Deskripsi (opsional)</label>
      <textarea name="description" rows="2" maxlength="500" class="form-control form-control-sm">{{ old('description', $type->description) }}</textarea>
    </div>

    <div class="mb-3">
      <label class="form-label small fw-medium text-dark">Urutan Tampil</label>
      <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $type->sort_order ?? 0) }}" class="form-control form-control-sm">
    </div>

    <label class="d-flex align-items-center gap-2 small text-dark mb-3">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active ?? true)) class="form-check-input" style="margin-top:0">
      Aktif (muncul sebagai pilihan dan tab)
    </label>

    <div class="d-flex align-items-center gap-2 pt-2 border-top">
      <button type="submit" class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check" style="font-size:11px"></i> Simpan</button>
      <a href="{{ route('admin.product-types.index') }}" class="btn btn-outline-secondary btn-sm mt-2">Batal</a>
    </div>
  </form>

@endsection
