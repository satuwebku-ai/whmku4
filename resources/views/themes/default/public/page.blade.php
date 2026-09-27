@extends('public.layout')

@php
  $seoTitle       = $page->seo_title;
  $seoDescription = $page->seo_description;
  $seoKeywords    = $page->meta_keywords;
  $seoImage       = $page->og_image;
  $seoNoindex     = $page->noindex;
@endphp

@section('content')
  <article class="card-public p-4 p-md-5">
    <h1 class="fw-bold text-dark mb-2" style="font-size:1.6rem">{{ $page->title }}</h1>
    <p class="text-muted mb-4" style="font-size:11px">Diperbarui {{ $page->updated_at->format('d M Y') }}</p>

    <div class="prose-content">
      {!! $page->content !!}
    </div>
  </article>
@endsection
