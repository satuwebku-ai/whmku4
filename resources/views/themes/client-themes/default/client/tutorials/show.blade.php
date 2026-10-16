@extends('client.layout')
@section('title', $article->title)

@section('content')
  <div class="mx-auto" style="max-width:52rem">
    <a href="{{ route('client.tutorials', $article->category ? ['category' => $article->category->slug] : []) }}" class="text-muted text-decoration-none small">
      <i class="fa-solid fa-arrow-left me-1"></i> Semua tutorial{{ $article->category ? ' · '.$article->category->name : '' }}
    </a>

    <article class="card-public p-4 p-md-5 mt-3">
      @if ($article->category)
        <p class="mb-2"><span class="badge rounded-pill bg-light text-secondary">{{ $article->category->name }}</span></p>
      @endif
      <h1 class="fw-bold text-dark mb-3" style="font-size:clamp(1.6rem,3.5vw,2.2rem)">{{ $article->title }}</h1>
      <p class="small text-muted mb-4">Diperbarui {{ $article->updated_at?->format('d M Y') }} · {{ number_format($article->views_count) }} kali dilihat</p>
      <div class="prose-content">{!! $article->safe_content !!}</div>
    </article>

    @if ($related->isNotEmpty())
      <h2 class="h6 fw-bold text-dark mt-4 mb-3">Tutorial lainnya</h2>
      <div class="row g-3">
        @foreach ($related as $item)
          <div class="col-12 col-md-4">
            <a href="{{ route('client.tutorials.show', $item->slug) }}" class="card-public d-block h-100 p-3 text-decoration-none">
              <p class="fw-semibold text-dark small mb-1">{{ $item->title }}</p>
              <span class="small" style="color:var(--lumora-theme)">Baca <i class="fa-solid fa-arrow-right ms-1"></i></span>
            </a>
          </div>
        @endforeach
      </div>
    @endif

    <div class="card-public p-4 mt-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <p class="fw-semibold text-dark mb-0">Masih butuh bantuan?</p>
        <p class="small text-muted mb-0">Ceritakan kendala Anda, tim kami akan membantu.</p>
      </div>
      <a href="{{ route('client.tickets.create') }}" class="btn btn-theme"><i class="fa-solid fa-life-ring me-1"></i> Buat Tiket</a>
    </div>
  </div>
@endsection
