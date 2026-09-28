@extends('public.layout')

@php
  use App\Models\Setting;

  $siteName = Setting::get('site_name', config('app.name'));
  $tagline  = Setting::get('site_tagline', 'Hosting cepat, domain murah, aktif dalam hitungan menit.');

  $seoTitle = $siteName . ' — Hosting & Domain Indonesia';
  $seoDescription = $tagline;
@endphp

@section('full-width')

  @include('public.partials.popup-banner')

  {{--
      Beranda tema NamaHost.

      Urutan ($homeOrder) dan tampil/tidaknya ($homeSections) disusun di
      CatalogController::homeData() dari
      Admin -> Sistem -> Pengaturan -> Halaman Depan.

      Section yang datanya kosong otomatis bernilai false, jadi tidak
      dirender walau toggle-nya menyala.

      Tiap section = partial public/home/_{nama}.blade.php. Karena file ini
      ada di folder tema namahost, @includeIf mencari partial namahost
      lebih dulu, baru jatuh balik ke tema default.
  --}}
  @foreach ($homeOrder as $section)
    @if ($homeSections[$section] ?? false)
      @includeIf('public.home._' . $section)
    @endif
  @endforeach

@endsection