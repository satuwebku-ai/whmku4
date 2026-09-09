@extends('layouts.admin')

@section('title', 'Banner Promo')

@section('content')

  @include('admin.pages._nav')

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Banner Promo</h1>
      <p class="small text-muted mb-0">Tampil di halaman utama situs publik — bisa lebih dari satu, bergantian.</p>
    </div>
    <a href="{{ route('admin.promo-banners.create') }}" class="btn btn-primary">
      <i class="fa-solid fa-plus" style="font-size:12px"></i> Tambah Banner
    </a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <div>
      @forelse ($banners as $banner)
        <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom {{ $banner->is_active ? '' : 'opacity-50' }}">

          <div class="d-flex flex-column flex-shrink-0" style="gap:2px">
            <form method="POST" action="{{ route('admin.promo-banners.move', $banner) }}">
              @csrf
              <input type="hidden" name="direction" value="up">
              <button type="submit" class="btn btn-link p-0 text-muted d-flex align-items-center justify-content-center" style="width:24px;height:20px" title="Naikkan">
                <i class="fa-solid fa-chevron-up" style="font-size:10px"></i>
              </button>
            </form>
            <form method="POST" action="{{ route('admin.promo-banners.move', $banner) }}">
              @csrf
              <input type="hidden" name="direction" value="down">
              <button type="submit" class="btn btn-link p-0 text-muted d-flex align-items-center justify-content-center" style="width:24px;height:20px" title="Turunkan">
                <i class="fa-solid fa-chevron-down" style="font-size:10px"></i>
              </button>
            </form>
          </div>

          <img src="{{ route('banner.file', $banner->image) }}" alt="{{ $banner->title }}" class="rounded-3 border flex-shrink-0" style="width:96px;height:56px;object-fit:cover">

          <div class="flex-grow-1 min-w-0">
            <p class="small fw-medium text-dark mb-0">{{ $banner->title }}</p>
            <p class="text-muted text-truncate mb-0" style="font-size:12px">
              {{ $banner->subtitle ?: '—' }}
              @if ($banner->link_url)
                · <i class="fa-solid fa-link" style="font-size:9px"></i> {{ $banner->link_url }}
              @endif
            </p>
            @if ($banner->starts_at || $banner->ends_at)
              <p class="text-muted mb-0 mt-1" style="font-size:11px">
                <i class="fa-regular fa-calendar" style="font-size:9px"></i>
                Tayang: {{ $banner->starts_at?->format('d M Y') ?? 'sekarang' }} — {{ $banner->ends_at?->format('d M Y') ?? 'tanpa batas' }}
              </p>
            @endif
          </div>

          <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <form method="POST" action="{{ route('admin.promo-banners.status') }}">
              @csrf
              <input type="hidden" name="banner_id" value="{{ $banner->id }}">
              <button type="submit" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0"
                      title="{{ $banner->is_active ? 'Sembunyikan' : 'Tampilkan' }}">
                <i class="fa-solid {{ $banner->is_active ? 'fa-eye' : 'fa-eye-slash' }}" style="font-size:12px"></i>
              </button>
            </form>
            <a href="{{ route('admin.promo-banners.edit', $banner) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0">
              <i class="fa-regular fa-pen-to-square" style="font-size:12px"></i>
            </a>
            <form method="POST" action="{{ route('admin.promo-banners.destroy', $banner) }}"
                  data-confirm="Hapus banner &quot;{{ $banner->title }}&quot;?" data-confirm-title="Hapus Banner" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;padding:0">
                <i class="fa-regular fa-trash-can" style="font-size:12px"></i>
              </button>
            </form>
          </div>
        </div>
      @empty
        <div class="text-center py-5">
          <p class="small text-dark mb-1">Belum ada banner promo.</p>
          <p class="text-muted mb-0" style="font-size:12px">Halaman utama situs publik akan tampil tanpa banner sampai kamu menambahkan satu.</p>
        </div>
      @endforelse
    </div>
  </div>

@endsection
