@extends('public.layout')

@php
  $seoTitle = $category ? 'Pusat Bantuan: ' . $category->name : 'Pusat Bantuan';
  $seoDescription = 'Panduan dan jawaban atas pertanyaan umum tentang layanan kami.';
@endphp

@section('content')
  <div class="mb-4">
    <p class="text-theme fw-semibold text-uppercase mb-2" style="font-size:11px;letter-spacing:.1em">Bantuan mandiri</p>
    <h1 class="fw-bold text-dark mb-2" style="font-size:clamp(1.8rem,4vw,2.5rem)">{{ $category ? $category->name : 'Pusat Bantuan' }}</h1>
    <p class="text-muted mb-3">Cari panduan untuk menyiapkan dan mengelola layanan Anda.</p>
    <form method="GET" action="{{ route('knowledge-base.index') }}" class="mx-auto" style="max-width:38rem">
      @if ($category)<input type="hidden" name="category" value="{{ $category->slug }}">@endif
      <div class="input-group input-group-lg">
        <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari panduan..." aria-label="Cari panduan">
        <button class="btn btn-theme" aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
      </div>
    </form>
  </div>

  <div class="row g-4">
    <aside class="col-12 col-lg-3">
      <div class="card-public p-3">
        <div class="fw-semibold text-dark mb-2">Topik</div>
        <div class="d-flex flex-column gap-1">
          <a href="{{ route('knowledge-base.index') }}" class="text-decoration-none rounded-2 px-2 py-1 {{ $category ? 'text-muted' : 'text-theme fw-semibold' }}">Semua topik</a>
          @foreach ($categories as $item)
            @if ($item->articles_count)
              <a href="{{ route('knowledge-base.index', ['category' => $item->slug]) }}" class="text-decoration-none rounded-2 px-2 py-1 {{ $category?->id === $item->id ? 'text-theme fw-semibold' : 'text-muted' }}">{{ $item->name }} <span class="small">({{ $item->articles_count }})</span></a>
            @endif
          @endforeach
        </div>
      </div>
    </aside>

    <div class="col-12 col-lg-9">
      <div class="card-public overflow-hidden">
        @forelse ($articles as $article)
          <a href="{{ route('knowledge-base.show', $article->slug) }}" class="d-flex align-items-center justify-content-between gap-3 p-3 p-md-4 text-decoration-none border-bottom">
            <span>
              <span class="d-block fw-semibold text-dark">{{ $article->title }}</span>
              @if ($article->category)<span class="small text-muted">{{ $article->category->name }}</span>@endif
            </span>
            <i class="fa-solid fa-chevron-right small text-muted"></i>
          </a>
        @empty
          <div class="p-5 text-center text-muted">Belum ada panduan yang cocok. Coba kata kunci lain.</div>
        @endforelse
      </div>
      @if ($articles->hasPages())<div class="mt-4">{{ $articles->links('pagination.bootstrap') }}</div>@endif
    </div>
  </div>
@endsection
