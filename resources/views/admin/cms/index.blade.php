@extends('layouts.admin')

@section('title', 'Pengelolaan CMS')

@section('content')
  @php
    // Tiap kartu = satu tab di baris navigasi CMS (admin/pages/_nav.blade.php).
    // Tambah bagian baru cukup di dua tempat itu.
    $sections = [
      ['label' => 'Beranda', 'desc' => 'Susunan section, teks hero, keunggulan, dan ajakan daftar.',
       'route' => 'admin.settings.homepage', 'cta' => 'Atur beranda', 'count' => null],
      ['label' => 'Halaman', 'desc' => 'Halaman statis seperti Tentang Kami atau Kebijakan Privasi.',
       'route' => 'admin.pages', 'cta' => 'Kelola halaman', 'count' => $counts['pages'] . ' halaman (' . $counts['published_pages'] . ' terbit)'],
      ['label' => 'Navigasi', 'desc' => 'Menu utama dan submenu yang tampil di situs publik.',
       'route' => 'admin.nav-menus', 'cta' => 'Kelola navigasi', 'count' => $counts['menus'] . ' menu'],
      ['label' => 'Promosi', 'desc' => 'Banner promo, popup, dan pengumuman untuk pengunjung.',
       'route' => 'admin.promo-banners.index', 'cta' => 'Kelola promosi', 'count' => $counts['banners'] . ' banner'],
      ['label' => 'Blog', 'desc' => 'Artikel blog beserta kategorinya.',
       'route' => 'admin.blog.index', 'cta' => 'Kelola blog', 'count' => $counts['posts'] . ' artikel, ' . $counts['categories'] . ' kategori'],
      ['label' => 'Pusat Bantuan', 'desc' => 'Panduan dan jawaban umum untuk pelanggan.',
       'route' => 'admin.knowledge-base.index', 'cta' => 'Pusat bantuan', 'count' => null],
      ['label' => 'Notifikasi', 'desc' => 'Isi email dan WhatsApp transaksional yang dikirim aplikasi.',
       'route' => 'admin.notification-templates.index', 'cta' => 'Template notifikasi', 'count' => null],
    ];
  @endphp

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Pengelolaan CMS</h1>
    <p class="small text-muted mb-0">Semua konten situs disimpan di database. Tambah atau ubah dari sini, tanpa menyentuh kode.</p>
  </div>

  <div class="row g-3">
    @foreach ($sections as $s)
      <div class="col-12 col-md-6 col-xl-4">
        <section class="card border rounded-4 p-4 h-100 d-flex flex-column">
          <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
            <h2 class="h6 fw-bold text-dark mb-0">{{ $s['label'] }}</h2>
            @if ($s['count'])
              <span class="badge badge-soft-secondary flex-shrink-0" style="font-size:10px">{{ $s['count'] }}</span>
            @endif
          </div>
          <p class="small text-muted">{{ $s['desc'] }}</p>
          <div class="mt-auto">
            <a href="{{ route($s['route']) }}" class="btn btn-primary btn-sm">{{ $s['cta'] }}</a>
          </div>
        </section>
      </div>
    @endforeach
  </div>
@endsection
