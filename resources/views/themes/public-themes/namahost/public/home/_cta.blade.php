{{-- ══════════ Ajakan (tema NamaHost) ══════════ --}}
@php
  $cta = \App\Support\HomeContent::cta([
    'title'     => 'Siap membuat website Anda online?',
    'text'      => 'Buat akun gratis, pilih paket, dan mulai online hari ini juga.',
    'primary'   => 'Lihat paket',
    'secondary' => 'Daftar gratis',
  ]);
@endphp
<section class="container py-5">
  <div class="cta p-4 p-lg-5 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <h2 class="h3 mb-1">{{ $cta['title'] }}</h2>
      <p class="mb-0 opacity-75">{{ $cta['text'] }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('catalog.index') }}" class="btn btn-light btn-lg">{{ $cta['primary'] }}</a>
      <a href="{{ route('client.register') }}" class="btn btn-accent btn-lg">{{ $cta['secondary'] }}</a>
    </div>
  </div>
</section>
