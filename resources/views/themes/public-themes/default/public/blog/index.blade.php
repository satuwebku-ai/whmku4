@extends('public.layout')

@php
  $seoTitle = $category ? 'Blog: ' . $category->name : 'Blog';
  $seoDescription = 'Artikel, kabar terbaru, dan panduan dari ' . \App\Models\Setting::get('site_name', config('app.name'));
@endphp

@section('content')
  <div class="mb-4">
    <p class="text-theme fw-semibold text-uppercase mb-2" style="font-size:11px;letter-spacing:.1em">Wawasan & kabar terbaru</p>
    <h1 class="fw-bold text-dark mb-2" style="font-size:clamp(1.8rem,4vw,2.5rem)">{{ $category ? $category->name : 'Blog' }}</h1>
    <p class="text-muted mb-0">Artikel dan informasi terbaru dari tim kami.</p>
  </div>

  <div class="row g-4">
    <aside class="col-12 col-lg-3">
      <div class="card-public p-3">
        <div class="fw-semibold text-dark mb-2">Kategori</div>
        <div class="d-flex flex-wrap gap-2">
          <a href="{{ route('blog.index') }}" class="badge rounded-pill text-decoration-none {{ $category ? 'bg-light text-secondary' : 'bg-primary' }}">Semua</a>
          @foreach ($categories as $item)
            @if ($item->posts_count)
              <a href="{{ route('blog.index', ['category' => $item->slug]) }}" class="badge rounded-pill text-decoration-none {{ $category?->id === $item->id ? 'bg-primary' : 'bg-light text-secondary' }}">{{ $item->name }} <span class="opacity-75">({{ $item->posts_count }})</span></a>
            @endif
          @endforeach
        </div>
        <form method="GET" action="{{ route('blog.index') }}" class="mt-3">
          @if ($category)<input type="hidden" name="category" value="{{ $category->slug }}">@endif
          <label for="blogSearch" class="form-label small text-muted">Cari artikel</label>
          <div class="input-group">
            <input id="blogSearch" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Kata kunci">
            <button class="btn btn-theme" aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
          </div>
        </form>
      </div>
    </aside>

    <div class="col-12 col-lg-9">
      <div class="row g-3">
        @forelse ($posts as $post)
          <div class="col-12 col-md-6">
            <article class="card-public h-100 overflow-hidden">
              @if ($post->cover_image)
                <a href="{{ route('blog.show', $post->slug) }}" class="d-block">
                  <img src="{{ $post->cover_image }}" alt="" loading="lazy" class="w-100" style="height:190px;object-fit:cover">
                </a>
              @endif
              <div class="p-4">
                <div class="d-flex align-items-center gap-2 small text-muted mb-2 flex-wrap">
                  @if ($post->category)<span class="badge rounded-pill bg-light text-secondary">{{ $post->category->name }}</span>@endif
                  <time datetime="{{ ($post->published_at ?? $post->created_at)?->toAtomString() }}">{{ ($post->published_at ?? $post->created_at)?->format('d M Y') }}</time>
                </div>
                <h2 class="h5 fw-bold mb-2"><a href="{{ route('blog.show', $post->slug) }}" class="text-dark text-decoration-none">{{ $post->title }}</a></h2>
                @if ($post->excerpt)<p class="text-muted mb-3">{{ $post->excerpt }}</p>@endif
                <a href="{{ route('blog.show', $post->slug) }}" class="text-theme fw-semibold text-decoration-none small">Baca artikel <i class="fa-solid fa-arrow-right ms-1"></i></a>
              </div>
            </article>
          </div>
        @empty
          <div class="col-12"><div class="card-public p-5 text-center text-muted">Belum ada artikel untuk ditampilkan.</div></div>
        @endforelse
      </div>
      @if ($posts->hasPages())<div class="mt-4">{{ $posts->links('pagination.bootstrap') }}</div>@endif
    </div>
  </div>
@endsection
