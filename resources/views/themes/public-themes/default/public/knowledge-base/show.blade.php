@extends('public.layout')

@php
  $seoTitle = $article->title;
  $seoDescription = \Illuminate\Support\Str::limit(strip_tags((string) $article->content), 155);
@endphp

@section('content')
  <div class="mx-auto" style="max-width:52rem">
    <a href="{{ route('knowledge-base.index', $article->category ? ['category' => $article->category->slug] : []) }}" class="text-muted text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Pusat Bantuan</a>
    <article class="card-public p-4 p-md-5 mt-3">
      @if ($article->category)<p class="mb-2"><span class="badge rounded-pill bg-light text-secondary">{{ $article->category->name }}</span></p>@endif
      <h1 class="fw-bold text-dark mb-3" style="font-size:clamp(1.8rem,4vw,2.5rem)">{{ $article->title }}</h1>
      <p class="small text-muted mb-4">Diperbarui {{ $article->updated_at?->format('d M Y') }} · {{ number_format($article->views_count) }} kali dilihat</p>
      <div class="prose-content">{!! $article->safe_content !!}</div>
    </article>
  </div>
@endsection
