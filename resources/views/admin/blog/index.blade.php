@extends('layouts.admin')

@section('title', 'Artikel Blog')

@section('content')
  @include('admin.pages._nav')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Artikel Blog</h1>
      <p class="small text-muted mb-0">Tulis artikel dan berita yang tampil di situs publik.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.blog.categories') }}" class="btn btn-outline-secondary btn-sm">Kelola Kategori</a>
      <a href="{{ route('admin.blog.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Artikel Baru</a>
    </div>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari judul artikel..." class="form-control form-control-sm" style="max-width:18rem">
      <select name="status" class="form-select form-select-sm" style="max-width:11rem">
        <option value="">Semua status</option>
        <option value="published" @selected(request('status') === 'published')>Terbit</option>
        <option value="draft" @selected(request('status') === 'draft')>Draf</option>
      </select>
      <button class="btn btn-outline-secondary btn-sm">Filter</button>
      @if (request('search') || request('status'))
        <a href="{{ route('admin.blog.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr class="small text-uppercase text-muted" style="background:#f8fafc">
          <th class="px-4 py-3">Artikel</th><th class="py-3">Kategori</th><th class="py-3">Penulis</th><th class="py-3">Status</th><th class="text-end px-4 py-3">Aksi</th>
        </tr></thead>
        <tbody>
          @forelse ($posts as $post)
            <tr>
              <td class="px-4 py-3">
                <div class="fw-medium text-dark">{{ $post->title }}</div>
                <div class="small text-muted">{{ ($post->published_at ?? $post->created_at)?->format('d M Y H:i') }}</div>
              </td>
              <td class="text-muted">{{ $post->category?->name ?? '—' }}</td>
              <td class="text-muted">{{ $post->author?->name ?? '—' }}</td>
              <td>
                @php($scheduled = $post->status === 'published' && $post->published_at?->isFuture())
                <span class="badge {{ $post->status === 'published' ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $scheduled ? 'Terjadwal' : ($post->status === 'published' ? 'Terbit' : 'Draf') }}</span>
              </td>
              <td class="text-end px-4 py-3">
                <div class="d-flex justify-content-end gap-2">
                  @if ($post->status === 'published' && $post->published_at?->lte(now()))
                    <a href="{{ route('blog.show', $post->slug) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm" title="Lihat artikel"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                  @endif
                  <form method="POST" action="{{ route('admin.blog.status', $post) }}">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm" title="{{ $post->status === 'published' ? 'Jadikan draf' : 'Terbitkan' }}"><i class="fa-solid {{ $post->status === 'published' ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i></button>
                  </form>
                  <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-outline-secondary btn-sm" title="Edit"><i class="fa-regular fa-pen-to-square"></i></a>
                  <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" data-confirm="Hapus artikel ini?" data-confirm-title="Hapus Artikel" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm" title="Hapus"><i class="fa-regular fa-trash-can"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-5">Belum ada artikel. Buat artikel pertama untuk mulai mengisi blog.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($posts->hasPages())<div class="px-4 py-3 border-top">{{ $posts->links('pagination.bootstrap') }}</div>@endif
  </div>
@endsection
