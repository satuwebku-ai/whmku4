{{--
  Pembungkus halaman toko yang dirender di domain CLIENT (keranjang).

  View toko meng-extend layout ini, bukan layout publik langsung, dan menaruh
  isinya di @section('store-content') (atau @section('full-width') untuk halaman
  berlatar penuh). Layout induknya dipilih otomatis oleh
  ThemeRegistry::storeLayout():
    - domain client (klien login ATAU tamu) -> layout portal client dalam MODE
      STORE: header belanja partials/store-topbar (mengikuti tema client)
      menggantikan sidebar dashboard, plus judul dan langkah pemesanan
      (partials/store-hero);
    - domain publik (etalase) -> layout publik apa adanya.

  View bisa mengatur header lewat variabel (didefinisikan di blok php sebelum
  section): $storeTitle, $storeSubtitle, $storeStep (1 Keranjang, 2 Checkout,
  3 Pembayaran).

  Jangan menulis direktif php (arobase + kata php) di komentar ini: compiler Blade
  mengekstrak blok itu SEBELUM membuang komentar dan akan merusak seluruh file. Satu file untuk semua tema: tema lain jatuh balik ke sini,
  warna mengikuti --lumora-theme tiap tema.
--}}
@php $storeLayout = \App\Support\ThemeRegistry::storeLayout(); @endphp
@extends($storeLayout)

@if ($storeLayout === 'client.layout')
  @section('title', $storeTitle ?? $seoTitle ?? 'Toko')
  @section('store-mode', '1')

  @section('content')
    <div class="mx-auto" style="max-width:80rem">
      @include('partials.store-hero', [
        'title'    => $storeTitle ?? $seoTitle ?? 'Toko',
        'subtitle' => $storeSubtitle ?? null,
        'step'     => $storeStep ?? null,
        'chips'    => false, // pintasan etalase sudah ada di header store
      ])

      @hasSection('full-width')
        <div class="rounded-4 overflow-hidden">
          @yield('full-width')
        </div>
      @else
        @yield('store-content')
      @endif
    </div>
  @endsection
@else
  @section('content')
    @yield('store-content')
  @endsection
@endif
