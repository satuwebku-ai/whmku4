{{-- ══════════ Keunggulan (tema Modern) ══════════ --}}
@php
  $benefits = [
    ['title' => 'Aktif Otomatis',     'desc' => 'Akun hosting dibuat otomatis begitu pembayaran masuk.'],
    ['title' => 'Aman & Terjaga',     'desc' => 'SSL gratis, backup rutin, dan proteksi berlapis.'],
    ['title' => 'Dukungan Responsif', 'desc' => 'Tim support siap membantu lewat tiket dan chat.'],
    ['title' => 'Bayar Mudah',        'desc' => 'Transfer bank, e-wallet, kartu kredit, dan QRIS.'],
  ];
@endphp
<section class="mx-section" style="padding-bottom:1rem">
  <div class="mx-container">
    <div class="mx-benefits">
      @foreach ($benefits as $item)
        <div class="mx-benefit">
          <span class="n">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
          <h3>{{ $item['title'] }}</h3>
          <p>{{ $item['desc'] }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
