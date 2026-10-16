@extends('layouts.admin')

@section('title', 'Jenis Produk')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Jenis Produk</h1>
      <p class="small text-muted mb-0">Jenis dipilih di tiap Kategori Produk dan menjadi tab di halaman Produk. Perilaku menentukan server, tagihan, dan URL katalog.</p>
    </div>
    <a href="{{ route('admin.product-types.create') }}" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah Jenis
    </a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Nama</th>
            <th class="py-3">Perilaku</th>
            <th class="text-center py-3">Kategori</th>
            <th class="text-center py-3">Urutan</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($types as $type)
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">
                <span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:{{ $type->color }}"></span>
                @if ($type->icon)<i class="fa-solid {{ $type->icon }} text-muted me-1"></i>@endif
                {{ $type->name }}
                <br><span class="text-muted fw-normal" style="font-size:11px">{{ $type->slug }}</span>
              </td>
              <td class="py-3"><span class="badge {{ $type->kind === 'vps' ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $type->kindLabel() }}</span></td>
              <td class="text-center text-muted py-3">{{ $type->categories_count }}</td>
              <td class="text-center text-muted py-3">{{ $type->sort_order }}</td>
              <td class="py-3"><span class="badge {{ $type->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $type->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <a href="{{ route('admin.product-types.edit', $type) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.product-types.destroy', $type) }}" data-confirm="Hapus jenis ini?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Belum ada jenis produk.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($types->hasPages())
      <div class="px-4 py-3 border-top">{{ $types->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
