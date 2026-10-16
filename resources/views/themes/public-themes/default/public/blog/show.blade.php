@extends('public.layout')

@php
  $seoTitle = $post->seo_title;
  $seoDescription = $post->seo_description;
@endphp

@section('content')
  <div class="mx-auto" style="max-width:52rem">
    <a href="{{ route('blog.index') }}" class="text-muted text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i> Kembali ke blog</a>
    <article class="card-public overflow-hidden mt-3">
      @if ($post->cover_image)
        <img src="{{ $post->cover_image }}" alt="" class="w-100" style="max-height:440px;object-fit:cover">
      @endif
      <div class="p-4 p-md-5">
        <div class="d-flex align-items-center gap-2 flex-wrap mb-3 small text-muted">
          @if ($post->category)<a href="{{ route('blog.index', ['category' => $post->category->slug]) }}" class="badge rounded-pill bg-light text-secondary text-decoration-none">{{ $post->category->name }}</a>@endif
          <time datetime="{{ ($post->published_at ?? $post->created_at)?->toAtomString() }}">{{ ($post->published_at ?? $post->created_at)?->format('d M Y') }}</time>
          @if ($post->author)<span>· {{ $post->author->name }}</span>@endif
        </div>
        <h1 class="fw-bold text-dark mb-4" style="font-size:clamp(1.8rem,4vw,2.5rem)">{{ $post->title }}</h1>
        @if ($post->excerpt)<p class="lead text-muted mb-4">{{ $post->excerpt }}</p>@endif
        <div class="prose-content">{!! $post->safe_content !!}</div>
      </div>
    </article>
  </div>
@endsection
