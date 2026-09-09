@extends('layouts.admin')

@section('title', 'Pengumuman')

@section('content')

  @include('admin.pages._nav')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Pengumuman</h1>
      <p class="small text-muted mb-0">Info maintenance, promo, dan gangguan layanan untuk klien.</p>
    </div>
    <a href="{{ route('admin.announcement.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Buat Pengumuman
    </a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari judul..." class="form-control form-control-sm" style="max-width:16rem;flex:1 1 180px">
      <select name="category" class="form-select" style="padding:.25rem .6rem;font-size:.875rem;border-radius:.375rem;max-width:10rem">
        <option value="">Semua Kategori</option>
        <option value="info" @selected(request('category') === 'info')>Info</option>
        <option value="promo" @selected(request('category') === 'promo')>Promo</option>
        <option value="maintenance" @selected(request('category') === 'maintenance')>Maintenance</option>
        <option value="incident" @selected(request('category') === 'incident')>Gangguan</option>
      </select>
      <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Filter</button>
      @if (request('search') || request('category'))
        <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Reset</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Judul</th>
            <th class="py-3">Kategori</th>
            <th class="py-3">Terbit</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @php
            $badgeMap = ['active' => 'badge-soft-success', 'pending' => 'badge-soft-warning', 'suspended' => 'badge-soft-danger', 'inactive' => 'badge-soft-secondary'];
          @endphp
          @forelse ($announcements as $item)
            <tr>
              <td class="px-4 py-3">
                <p class="fw-medium text-dark mb-0">
                  {{ $item->title }}
                  @if ($item->is_pinned)
                    <i class="fa-solid fa-thumbtack text-accent ms-1" style="font-size:10px" title="Disematkan"></i>
                  @endif
                </p>
                <a href="{{ route('announcements.show', $item->slug) }}" target="_blank" class="text-decoration-none text-muted" style="font-size:12px">/announcements/{{ $item->slug }}</a>
              </td>
              <td class="py-3"><span class="badge {{ $badgeMap[$item->category_badge] ?? 'badge-soft-secondary' }} text-capitalize">{{ $item->category }}</span></td>
              <td class="text-muted py-3" style="font-size:12px">{{ $item->published_at?->format('d M Y H:i') ?? '—' }}</td>
              <td class="py-3">
                <span class="badge {{ $item->is_published ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $item->is_published ? 'Terbit' : 'Draf' }}</span>
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <a href="{{ route('admin.announcement.edit.page', $item) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.announcement.delete', $item) }}" data-confirm="Hapus pengumuman ini?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-5">Belum ada pengumuman.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($announcements->hasPages())
      <div class="px-4 py-3 border-top">{{ $announcements->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
