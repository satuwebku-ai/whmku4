@extends('public.layout')

@php
  $seoTitle       = 'Pengumuman';
  $seoDescription = 'Informasi terbaru, jadwal maintenance, dan promo layanan kami.';
@endphp

@section('content')
  <h1 class="fw-bold text-dark mb-4" style="font-size:1.6rem">Pengumuman</h1>

  <div class="d-flex flex-column gap-3">
    @forelse ($announcements as $item)
      <a href="{{ route('announcements.show', $item->slug) }}" class="card-public p-4 text-decoration-none">
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
          @if ($item->is_pinned)
            <span class="badge rounded-pill" style="font-size:11px;background:rgba(79,70,229,.12);color:#4338ca">Disematkan</span>
          @endif
          <span class="badge rounded-pill text-capitalize" style="font-size:11px;background:#f1f5f9;color:#475569">{{ $item->category }}</span>
          <span class="text-muted" style="font-size:12px">{{ $item->published_at?->format('d M Y') }}</span>
        </div>
        <h2 class="fw-semibold text-dark mb-1" style="font-size:16px">{{ $item->title }}</h2>
        @if ($item->excerpt)
          <p class="text-muted mb-0" style="font-size:14px">{{ $item->excerpt }}</p>
        @endif
      </a>
    @empty
      <div class="card-public p-5 text-center text-muted">Belum ada pengumuman.</div>
    @endforelse
  </div>

  @if ($announcements->hasPages())
    <div class="mt-4">{{ $announcements->links('pagination.bootstrap') }}</div>
  @endif
@endsection
