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
      Susunan beranda dirender dari $homeOrder (urutan) + $homeSections
      (tampil/tidak). Keduanya disusun di CatalogController::homeData().

      Section yang datanya KOSONG otomatis bernilai false di
      $homeSections, jadi tidak dirender walau toggle-nya menyala --
      inilah yang mencegah blok kosong menganga di beranda (mis. judul
      "VPS & Cloud Server" tanpa satu pun kartu di bawahnya).

      Menambah section baru: buat partial di public/home/_nama.blade.php,
      lalu daftarkan namanya di $defaultOrder + $sectionData pada
      CatalogController.
  --}}
  @foreach ($homeOrder as $section)
    @if ($homeSections[$section] ?? false)
      @includeIf('public.home._' . $section)
    @endif
  @endforeach

@endsection
