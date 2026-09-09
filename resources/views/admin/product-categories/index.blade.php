@extends('layouts.admin')

@section('title', 'Kategori Produk')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Kategori Produk</h1>
      <p class="small text-muted mb-0">Kelompok produk di katalog, mis. Shared Hosting, WordPress Hosting, VPS.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-box" style="font-size:11px"></i> Lihat Produk
      </a>
      <a href="{{ route('admin.product-categories.create') }}" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah Kategori
      </a>
    </div>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Nama</th>
            <th class="py-3">Slug</th>
            <th class="text-center py-3">Produk</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($categories as $category)
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">
                @if ($category->icon)<i class="fa-solid {{ $category->icon }} text-muted me-1"></i>@endif
                {{ $category->name }}
              </td>
              <td class="text-muted py-3" style="font-size:12px">
                /{{ $category->urlSection() }}/{{ $category->slug }}
                <span class="badge {{ ($category->type ?? 'hosting') === 'vps' ? 'badge-soft-success' : 'badge-soft-secondary' }} ms-1" style="font-size:9px">
                  {{ ($category->type ?? 'hosting') === 'vps' ? 'VPS' : 'Hosting' }}
                </span>
              </td>
              <td class="text-center text-muted py-3">{{ $category->products_count }}</td>
              <td class="py-3">
                <span class="badge {{ $category->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <a href="{{ route('admin.product-categories.edit', $category) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.product-categories.destroy', $category) }}"
                        data-confirm="Hapus kategori {{ $category->name }}?" data-confirm-title="Hapus Kategori"
                        data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-5">Belum ada kategori produk.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($categories->hasPages())
      <div class="px-4 py-3 border-top">{{ $categories->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
