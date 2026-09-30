@extends('public.layout')

@php
  $seoTitle = 'Paket VPS';
  $seoDescription = 'VPS NVMe dengan akses root penuh dan aktivasi otomatis dalam hitungan menit.';
@endphp

@section('content')

  @include('public._promo-banner-carousel')

  <div class="text-center mb-5 mx-auto" style="max-width:40rem">
    <h1 class="fw-bold text-dark mb-3" style="font-size:1.9rem">Paket VPS NVMe</h1>
    <p class="text-muted mb-0">Kendali penuh lewat akses root, aktif otomatis dalam hitungan menit. Naik kelas kapan saja saat kebutuhan bertambah.</p>
    <div class="mt-4 d-flex justify-content-center gap-2 flex-wrap">
      <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary">Lihat Paket Hosting</a>
      <a href="{{ route('domain.search') }}" class="btn btn-outline-secondary">
        <i class="fa-solid fa-magnifying-glass" style="font-size:12px"></i> Cek Ketersediaan Domain
      </a>
    </div>
  </div>

  @if ($products->isEmpty())
    <div class="card-public p-5 text-center text-muted" style="font-size:14px">Paket VPS sedang disiapkan. Silakan cek kembali nanti.</div>
  @else
    <div class="mb-5">
      <div class="row g-3 justify-content-center">
        @foreach ($products as $product)
          <div class="col-sm-6 col-lg-4">
            @include('public.catalog._product-card', ['product' => $product])
          </div>
        @endforeach
      </div>
    </div>
  @endif

  @if ($categories->count() > 1)
    <div>
      <h2 class="fw-bold text-dark mb-3" style="font-size:1.15rem">Kategori VPS</h2>
      <div class="row g-3">
        @foreach ($categories as $category)
          <div class="col-sm-6 col-lg-4">
            <a href="{{ $category->publicUrl() }}" class="card-public p-4 text-decoration-none d-block h-100">
              <h3 class="fw-semibold text-dark mb-1" style="font-size:15px">{{ $category->name }}</h3>
              @if ($category->description)
                <p class="text-muted mb-2" style="font-size:14px">{{ $category->description }}</p>
              @endif
              <p class="text-muted mb-0" style="font-size:12px">{{ $category->products_count }} paket tersedia</p>
            </a>
          </div>
        @endforeach
      </div>
    </div>
  @endif

@endsection
