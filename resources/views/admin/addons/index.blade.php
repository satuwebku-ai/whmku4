@extends('layouts.admin')

@section('title', 'Addons')

@section('content')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Addons</h1>
      <p class="small text-muted mb-0">Fitur tambahan yang bisa dipasang klien di layanan hosting mereka (IP Dedicated, Backup, dll).</p>
    </div>
    <a href="{{ route('admin.addons.create') }}" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah Addon
    </a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Nama</th>
            <th class="text-end py-3">Bulanan</th>
            <th class="text-end py-3">Tahunan</th>
            <th class="text-center py-3">Dipakai</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($addons as $addon)
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">
                <a href="{{ route('admin.addons.edit', $addon) }}" class="text-decoration-none text-dark">{{ $addon->name }}</a>
              </td>
              <td class="text-end text-muted py-3">{{ $addon->price_monthly ? 'Rp ' . number_format($addon->price_monthly, 0, ',', '.') : '—' }}</td>
              <td class="text-end text-muted py-3">{{ $addon->price_annually ? 'Rp ' . number_format($addon->price_annually, 0, ',', '.') : '—' }}</td>
              <td class="text-center text-muted py-3">{{ $addon->attachments_count }}</td>
              <td class="py-3">
                <form method="POST" action="{{ route('admin.addon.status') }}">
                  @csrf
                  <input type="hidden" name="addon_id" value="{{ $addon->id }}">
                  <button type="submit" class="badge border-0 {{ $addon->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}" style="cursor:pointer">
                    {{ $addon->is_active ? 'Aktif' : 'Nonaktif' }}
                  </button>
                </form>
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <a href="{{ route('admin.addons.edit', $addon) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.addons.destroy', $addon) }}"
                        data-confirm="Hapus addon {{ $addon->name }}?" data-confirm-title="Hapus Addon" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Belum ada addon. Klik "Tambah Addon" untuk membuat yang pertama.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($addons->hasPages())
      <div class="px-4 py-3 border-top">{{ $addons->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
