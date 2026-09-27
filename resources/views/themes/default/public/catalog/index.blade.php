@extends('public.layout')

@php
  $seoTitle = 'Paket Hosting & Domain';
  $seoDescription = 'Pilih paket hosting sesuai kebutuhan Anda — mulai dari shared hosting hingga VPS, lengkap dengan domain.';
@endphp

@section('content')

  @include('public._promo-banner-carousel')

  @if (request('dari_domain'))
    <div class="card-public p-3 mb-4 d-flex align-items-center justify-content-between gap-3 flex-wrap" style="border-color:rgba(79,70,229,.25)!important;background:rgba(79,70,229,.04)">
      <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center gap-2" style="font-size:12px">
          <span class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:24px;height:24px;font-size:11px;background:#e2e8f0;color:#64748b">✓</span>
          <span class="text-muted">Domain</span>
          <span style="width:32px;height:1px;background:#e2e8f0"></span>
          <span class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="width:24px;height:24px;font-size:11px;background:var(--lumora-theme)">2</span>
          <span class="fw-semibold text-dark">Hosting</span>
        </div>
        <p class="text-muted mb-0 d-none d-sm-block" style="font-size:14px">
          Pilih paket di bawah untuk didampingkan dengan domain Anda, atau lewati kalau cuma butuh domainnya saja.
        </p>
      </div>
      <a href="{{ route('cart.index') }}" class="btn btn-outline-secondary btn-sm flex-shrink-0">
        Lewati — Cuma Domain Saja <i class="fa-solid fa-arrow-right" style="font-size:12px"></i>
      </a>
    </div>
  @endif

  <div class="text-center mb-5 mx-auto" style="max-width:40rem">
    <h1 class="fw-bold text-dark mb-3" style="font-size:1.9rem">Paket Hosting untuk Setiap Kebutuhan</h1>
    <p class="text-muted mb-0">Dari website pribadi sampai toko online — pilih paket yang pas, aktif dalam hitungan menit.</p>
    <div class="mt-4">
      <a href="{{ route('domain.search') }}" class="btn btn-outline-secondary">
        <i class="fa-solid fa-magnifying-glass" style="font-size:12px"></i> Cek Ketersediaan Domain
      </a>
    </div>
  </div>

  @if ($featured->isNotEmpty())
    <div class="mb-5">
      <h2 class="fw-bold text-dark mb-3" style="font-size:1.15rem">Paket Unggulan</h2>
      <div class="row g-3">
        @foreach ($featured as $product)
          <div class="col-sm-6 col-lg-4">
            @include('public.catalog._product-card', ['product' => $product])
          </div>
        @endforeach
      </div>
    </div>
  @endif

  <div>
    <h2 class="fw-bold text-dark mb-3" style="font-size:1.15rem">Kategori</h2>

    @if ($categories->isEmpty())
      <div class="card-public p-5 text-center text-muted" style="font-size:14px">Katalog sedang disiapkan. Silakan cek kembali nanti.</div>
    @else
      <div class="row g-3">
        @foreach ($categories as $category)
          <div class="col-sm-6 col-lg-4">
            <a href="{{ $category->publicUrl() }}" class="card-public p-4 text-decoration-none d-block h-100 cat-card">
              <div class="d-flex align-items-start justify-content-between mb-3">
                <span class="rounded-4 d-flex align-items-center justify-content-center cat-icon" style="width:44px;height:44px;background:rgba(79,70,229,.12);color:#4f46e5;transition:transform .15s ease">
                  <i class="fa-solid {{ $category->icon ?: 'fa-box' }}"></i>
                </span>
                <i class="fa-solid fa-arrow-right cat-arrow" style="font-size:12px;color:var(--lumora-theme);opacity:0;transform:translateX(-4px);transition:opacity .15s ease,transform .15s ease"></i>
              </div>
              <h3 class="fw-semibold text-dark mb-1" style="font-size:15px">{{ $category->name }}</h3>
              @if ($category->description)
                <p class="text-muted mb-2" style="font-size:14px">{{ $category->description }}</p>
              @endif
              <p class="text-muted mb-0" style="font-size:12px">{{ $category->products_count }} paket tersedia</p>
            </a>
          </div>
        @endforeach
      </div>

      <style>
        .cat-card{ transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease; }
        .cat-card:hover{ transform:translateY(-3px); border-color:rgba(79,70,229,.35)!important; box-shadow:0 10px 24px -12px rgba(79,70,229,.35); }
        .cat-card:hover .cat-icon{ transform:scale(1.08); }
        .cat-card:hover .cat-arrow{ opacity:1; transform:translateX(0); }
      </style>
    @endif
  </div>

@endsection
