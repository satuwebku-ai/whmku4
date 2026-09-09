@extends('layouts.admin')

@section('title', 'Halaman')

@section('content')

  @include('admin.pages._nav')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Halaman Statis</h1>
      <p class="small text-muted mb-0">Kelola halaman seperti Tentang Kami, Syarat & Ketentuan, Kebijakan Privasi.</p>
    </div>
    <a href="{{ route('admin.page.add.page') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Tambah Halaman
    </a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari judul halaman..." class="form-control form-control-sm" style="max-width:16rem;flex:1 1 180px">
      <button type="submit" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Cari</button>
      @if (request('search'))
        <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm" style="width:fit-content">Reset</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">Judul</th>
            <th class="py-3">URL</th>
            <th class="py-3">SEO</th>
            <th class="py-3">Footer</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($pages as $page)
            <tr>
              <td class="px-4 py-3 fw-medium text-dark">{{ $page->title }}</td>
              <td class="text-muted py-3" style="font-size:12px">
                <a href="{{ route('page.show', $page->slug) }}" target="_blank" class="text-decoration-none text-muted">/{{ $page->slug }}</a>
              </td>
              <td class="py-3">
                @if ($page->meta_description)
                  <span class="badge badge-soft-success">Lengkap</span>
                @else
                  <span class="badge badge-soft-warning">Belum diisi</span>
                @endif
                @if ($page->noindex)
                  <span class="badge badge-soft-danger ms-1">noindex</span>
                @endif
              </td>
              <td class="text-muted py-3">{{ $page->show_in_footer ? 'Ya' : '—' }}</td>
              <td class="py-3">
                <span class="badge {{ $page->is_published ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $page->is_published ? 'Terbit' : 'Draf' }}</span>
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <form method="POST" action="{{ route('admin.page.status') }}">
                    @csrf
                    <input type="hidden" name="page_id" value="{{ $page->id }}">
                    <button type="submit" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="{{ $page->is_published ? 'Jadikan draf' : 'Terbitkan' }}">
                      <i class="fa-solid {{ $page->is_published ? 'fa-toggle-on' : 'fa-toggle-off' }}" style="font-size:12px"></i>
                    </button>
                  </form>
                  <a href="{{ route('admin.page.edit.page', $page) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Edit">
                    <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
                  </a>
                  <form method="POST" action="{{ route('admin.page.delete', $page) }}" data-confirm="Hapus halaman ini?" data-confirm-title="Hapus Data" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0" title="Hapus">
                      <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">Belum ada halaman.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($pages->hasPages())
      <div class="px-4 py-3 border-top">{{ $pages->links('pagination.bootstrap') }}</div>
    @endif
  </div>

@endsection
