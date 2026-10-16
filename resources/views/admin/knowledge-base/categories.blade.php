@extends('layouts.admin')

@section('title', 'Kategori Pusat Bantuan')

@section('content')
  @include('admin.pages._nav')
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div><h1 class="h4 fw-bold text-dark mb-1">Kategori Pusat Bantuan</h1><p class="small text-muted mb-0">Artikel tetap tersimpan jika kategori dihapus.</p></div>
    <a href="{{ route('admin.knowledge-base.index') }}" class="btn btn-outline-secondary btn-sm">Kembali ke Artikel</a>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <form method="POST" action="{{ route('admin.knowledge-base.categories.store') }}" class="card border rounded-4 p-4">
        @csrf
        <h2 class="h6 fw-bold mb-3">Tambah Kategori</h2>
        <label class="form-label small">Nama</label>
        <input name="name" value="{{ old('name') }}" class="form-control mb-3" required maxlength="255">
        @error('name')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <label class="form-label small">Slug (opsional)</label>
        <input name="slug" value="{{ old('slug') }}" class="form-control mb-3" maxlength="255" placeholder="Otomatis dari nama">
        @error('slug')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <label class="form-label small">Urutan</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" class="form-control mb-3">
        @error('sort_order')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <button class="btn btn-primary">Simpan Kategori</button>
      </form>
    </div>
    <div class="col-12 col-lg-8">
      <div class="card border rounded-4 overflow-hidden">
        <div class="table-responsive"><table class="table align-middle mb-0">
          <thead><tr class="small text-uppercase text-muted" style="background:#f8fafc"><th class="px-3 py-3">Nama, slug & urutan</th><th>Artikel</th><th class="text-end px-3">Aksi</th></tr></thead>
          <tbody>
            @forelse ($categories as $category)
              <tr>
                <td>
                  <form id="category-{{ $category->id }}" method="POST" action="{{ route('admin.knowledge-base.categories.update', $category) }}">
                    @csrf
                    <input name="name" value="{{ $category->name }}" class="form-control form-control-sm mb-1" required maxlength="255" aria-label="Nama kategori">
                    <div class="d-flex gap-1">
                      <input name="slug" value="{{ $category->slug }}" class="form-control form-control-sm" required maxlength="255" aria-label="Slug kategori">
                      <input type="number" name="sort_order" value="{{ $category->sort_order }}" min="0" class="form-control form-control-sm" style="max-width:6rem" aria-label="Urutan">
                    </div>
                  </form>
                </td>
                <td>{{ $category->articles_count }}</td>
                <td class="text-end px-3">
                  <div class="d-flex justify-content-end gap-2">
                    <button form="category-{{ $category->id }}" class="btn btn-outline-primary btn-sm">Simpan</button>
                    <form method="POST" action="{{ route('admin.knowledge-base.categories.destroy', $category) }}" data-confirm="Hapus kategori ini? Artikelnya tetap disimpan tanpa kategori." data-confirm-style="danger">
                      @csrf @method('DELETE')
                      <button class="btn btn-outline-danger btn-sm" aria-label="Hapus kategori"><i class="fa-regular fa-trash-can"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted py-5">Belum ada kategori.</td></tr>
            @endforelse
          </tbody>
        </table></div>
        @if ($categories->hasPages())<div class="px-3 py-3 border-top">{{ $categories->links('pagination.bootstrap') }}</div>@endif
      </div>
    </div>
  </div>
@endsection
