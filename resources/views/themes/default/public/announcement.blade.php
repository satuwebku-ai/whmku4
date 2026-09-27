@extends('public.layout')

@php
  $seoTitle       = $announcement->seo_title;
  $seoDescription = $announcement->seo_description;
@endphp

@section('content')
  <a href="{{ route('announcements.index') }}" class="text-muted text-decoration-none" style="font-size:12px">&larr; Kembali ke Pengumuman</a>

  <article class="card-public p-4 p-md-5 mt-3">
    <div class="d-flex align-items-center gap-2 mb-3">
      <span class="badge rounded-pill text-capitalize" style="font-size:11px;background:#f1f5f9;color:#475569">{{ $announcement->category }}</span>
      <span class="text-muted" style="font-size:12px">{{ $announcement->published_at?->format('d M Y H:i') }}</span>
    </div>

    <h1 class="fw-bold text-dark mb-4" style="font-size:1.6rem">{{ $announcement->title }}</h1>

    <div class="prose-content">
      {!! $announcement->content !!}
    </div>
  </article>
@endsection
