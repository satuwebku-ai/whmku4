@extends('public.layout')

@php
  $seoTitle = $category->name;
  $seoDescription = $category->description ?: "Pilihan paket {$category->name} — aktif cepat, dukungan 24/7.";
@endphp

@section('content')

  <nav class="text-muted mb-3" style="font-size:12px">
    <a href="{{ route('catalog.index') }}" class="text-decoration-none text-muted">Hosting</a> / {{ $category->name }}
  </nav>

  <div class="mb-4">
    <h1 class="fw-bold text-dark mb-0" style="font-size:1.6rem">{{ $category->name }}</h1>
    @if ($category->description)
      <p class="text-muted mt-1 mb-0">{{ $category->description }}</p>
    @endif
  </div>

  @if ($products->isEmpty())
    <div class="card-public p-5 text-center text-muted" style="font-size:14px">Belum ada produk di kategori ini.</div>
  @else
    <div class="row g-3">
      @foreach ($products as $product)
        <div class="col-sm-6 col-lg-4">
          @include('public.catalog._product-card', ['product' => $product])
        </div>
      @endforeach
    </div>

    @if ($products->hasPages())
      <div class="mt-4">{{ $products->links('pagination.bootstrap') }}</div>
    @endif
  @endif

@endsection
