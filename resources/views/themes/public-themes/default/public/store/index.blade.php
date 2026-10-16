@extends('public.store-layout')

@php
  $seoTitle = 'Store';
  $storeTitle = 'Store';
  $storeSubtitle = 'Pilih layanan yang Anda butuhkan, lalu selesaikan pesanan di keranjang.';

  // [judul, keterangan, route, ikon, warna aksen]
  $tiles = [
    ['Pesan Hosting', 'Shared hosting cepat dan stabil untuk website Anda.', 'catalog.index', 'fa-cart-plus', '#4f46e5'],
    ['Pesan VPS', 'Server virtual dengan akses penuh dan sumber daya khusus.', 'catalog.vps', 'fa-cloud', '#0ea5e9'],
    ['Cek & Daftar Domain', 'Cari nama domain, daftarkan baru, atau transfer yang sudah ada.', 'domain.search', 'fa-magnifying-glass', '#10b981'],
    ['Beli Lisensi', 'Lisensi software dan add-on untuk melengkapi layanan Anda.', 'license.index', 'fa-key', '#f59e0b'],
    ['Keranjang', $cartCount > 0 ? $cartCount.' item siap diperiksa dan di-checkout.' : 'Keranjang Anda masih kosong.', 'cart.index', 'fa-cart-shopping', '#f43f5e'],
  ];
@endphp

@section('store-content')
  <style>
    .store-tile { display: flex; flex-direction: column; gap: .85rem; height: 100%; padding: 1.4rem; text-decoration: none; color: inherit;
      background: #fff; border: 1px solid #e8ebf0; border-radius: 1rem; box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
      transition: transform .15s, box-shadow .15s, border-color .15s; }
    .store-tile:hover { transform: translateY(-3px); box-shadow: 0 16px 30px -16px rgba(15, 23, 42, .25); border-color: var(--tile, #4f46e5); color: inherit; }
    .store-tile__icon { position: relative; width: 3rem; height: 3rem; border-radius: .9rem; display: flex; align-items: center; justify-content: center;
      font-size: 1.15rem; color: var(--tile, #4f46e5); background: color-mix(in srgb, var(--tile, #4f46e5) 12%, #fff); }
    .store-tile__title { margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
    .store-tile__desc { margin: 0; font-size: .875rem; color: #64748b; }
    .store-tile__go { margin-top: auto; font-size: .82rem; font-weight: 600; color: var(--tile, #4f46e5); }
    .store-tile__badge { position: absolute; top: -.35rem; right: -.35rem; min-width: 1.2rem; height: 1.2rem; padding: 0 .3rem; border-radius: 999px;
      background: #f43f5e; color: #fff; font-size: .7rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
  </style>

  <div class="row g-3 g-lg-4">
    @foreach ($tiles as [$label, $desc, $route, $icon, $color])
      <div class="col-12 col-sm-6 col-lg-4">
        <a href="{{ \App\Support\StoreUrl::route($route) }}" class="store-tile" style="--tile:{{ $color }}">
          <span class="store-tile__icon">
            <i class="fa-solid {{ $icon }}"></i>
            @if ($route === 'cart.index' && $cartCount > 0)
              <span class="store-tile__badge">{{ $cartCount }}</span>
            @endif
          </span>
          <h2 class="store-tile__title">{{ $label }}</h2>
          <p class="store-tile__desc">{{ $desc }}</p>
          <span class="store-tile__go">Buka <i class="fa-solid fa-arrow-right ms-1"></i></span>
        </a>
      </div>
    @endforeach
  </div>
@endsection
