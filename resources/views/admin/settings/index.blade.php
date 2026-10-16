@extends('layouts.admin')
@section('title', 'Pengaturan')
@section('content')

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Pengaturan</h1>
    <p class="small text-muted mb-0">Konfigurasi sistem dikelompokkan di sini. Beranda, halaman, menu, banner, dan konten situs dikelola dari satu menu CMS.</p>
  </div>

  @foreach ($groups as $groupLabel => $cards)
  <p class="text-uppercase text-muted fw-semibold mb-2 {{ $loop->first ? '' : 'mt-4' }}" style="font-size:11px;letter-spacing:.14em">{{ $groupLabel }}</p>
  <div class="row g-3">
    @foreach ($cards as $card)
      <div class="col-12 col-md-6 col-xl-4">
        <a href="{{ route($card['route']) }}" class="settings-card d-flex align-items-start gap-3 card border rounded-4 p-4 h-100 text-decoration-none">
          <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                style="width:44px;height:44px;background:rgba(79,70,229,.1);color:#4f46e5">
            <i class="fa-solid {{ $card['icon'] }}"></i>
          </span>
          <span class="min-w-0">
            <span class="d-block fw-bold text-dark mb-1" style="font-size:15px">{{ $card['label'] }}</span>
            <span class="d-block text-muted" style="font-size:12px;line-height:1.6">{{ $card['desc'] }}</span>
          </span>
        </a>
      </div>
    @endforeach
  </div>
  @endforeach

  <style>
    .settings-card { transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease; }
    .settings-card:hover {
      transform: translateY(-2px);
      border-color: #c7d2fe !important;
      box-shadow: 0 8px 20px rgba(79,70,229,.08);
    }
  </style>
@endsection
