@extends('client.layout')
@section('title', 'Tutorial')

@section('content')
  @include('partials.store-hero', [
    'title'        => 'Tutorial',
    'subtitle'     => 'Panduan langkah demi langkah untuk mengelola layanan Anda.',
    'eyebrow'      => 'Pusat Belajar',
    'eyebrowIcon'  => 'fa-graduation-cap',
  ])

  <form method="GET" action="{{ route('client.tutorials') }}" class="d-flex flex-wrap gap-2 mb-3">
    @if ($category)<input type="hidden" name="category" value="{{ $category->slug }}">@endif
    <div class="position-relative flex-grow-1" style="max-width:26rem">
      <i class="fa-solid fa-magnifying-glass position-absolute text-muted" style="left:.85rem;top:50%;transform:translateY(-50%);font-size:.8rem"></i>
      <input type="search" name="search" value="{{ $search }}" placeholder="Cari tutorial…" class="form-control" style="padding-left:2.2rem">
    </div>
    <button type="submit" class="btn btn-outline-secondary">Cari</button>
    @if ($search !== '' || $category)
      <a href="{{ route('client.tutorials') }}" class="btn btn-link text-muted">Reset</a>
    @endif
  </form>

  @if ($categories->isNotEmpty())
    <div class="d-flex gap-2 mb-4 pb-1" style="overflow-x:auto">
      <a href="{{ route('client.tutorials', array_filter(['search' => $search])) }}"
         class="px-3 py-2 rounded-pill text-decoration-none text-nowrap"
         style="font-size:12.5px;font-weight:500;{{ ! $category ? 'background:var(--lumora-theme);color:#fff' : 'background:#fff;border:1px solid #e2e8f0;color:#475569' }}">Semua</a>
      @foreach ($categories as $cat)
        <a href="{{ route('client.tutorials', array_filter(['category' => $cat->slug, 'search' => $search])) }}"
           class="px-3 py-2 rounded-pill text-decoration-none text-nowrap"
           style="font-size:12.5px;font-weight:500;{{ $category?->id === $cat->id ? 'background:var(--lumora-theme);color:#fff' : 'background:#fff;border:1px solid #e2e8f0;color:#475569' }}">
          {{ $cat->name }} <span style="opacity:.7">{{ $cat->articles_count }}</span>
        </a>
      @endforeach
    </div>
  @endif

  @if ($articles->isEmpty())
    <div class="card-public p-5 text-center">
      <i class="fa-solid fa-book-open text-muted mb-3" style="font-size:2rem"></i>
      <p class="fw-semibold text-dark mb-1">
        {{ $search !== '' || $category ? 'Tidak ada tutorial yang cocok.' : 'Belum ada tutorial.' }}
      </p>
      <p class="small text-muted mb-3">Butuh bantuan langsung? Tim kami siap membantu lewat tiket.</p>
      <a href="{{ route('client.tickets.create') }}" class="btn btn-theme"><i class="fa-solid fa-life-ring me-1"></i> Buat Tiket</a>
    </div>
  @else
    <div class="row g-3">
      @foreach ($articles as $article)
        <div class="col-12 col-md-6 col-xl-4">
          <a href="{{ route('client.tutorials.show', $article->slug) }}" class="card-public d-flex flex-column h-100 p-4 text-decoration-none">
            @if ($article->category)
              <span class="badge rounded-pill bg-light text-secondary align-self-start mb-2">{{ $article->category->name }}</span>
            @endif
            <h2 class="h6 fw-bold text-dark mb-2">{{ $article->title }}</h2>
            <p class="small text-muted mb-3" style="line-height:1.6">{{ \Illuminate\Support\Str::limit(strip_tags((string) $article->content), 120) }}</p>
            <div class="mt-auto d-flex align-items-center justify-content-between small text-muted">
              <span><i class="fa-regular fa-clock me-1"></i>{{ $article->updated_at?->format('d M Y') }}</span>
              <span class="fw-medium" style="color:var(--lumora-theme)">Baca <i class="fa-solid fa-arrow-right ms-1"></i></span>
            </div>
          </a>
        </div>
      @endforeach
    </div>

    @if ($articles->hasPages())
      <div class="mt-4">{{ $articles->links('pagination.bootstrap') }}</div>
    @endif
  @endif
@endsection
