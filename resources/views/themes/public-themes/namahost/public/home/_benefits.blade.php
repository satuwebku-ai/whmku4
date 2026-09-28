{{-- ══════════ Keunggulan + 3 langkah (tema NamaHost) ══════════ --}}
@php
  $features = [
    ['t-teal',   'bi-lightning-charge', 'Server cepat',          'LiteSpeed dan NVMe membuat website terbuka di bawah 1 detik.'],
    ['t-coral',  'bi-shield-check',     'Aman',                  'SSL gratis, firewall, dan pemindai malware otomatis.'],
    ['t-indigo', 'bi-arrow-repeat',     'Backup otomatis',       'Pulihkan file dan database sendiri dari area klien.'],
    ['t-amber',  'bi-headset',          'Bantuan ramah',         'Tim support siap membantu lewat tiket dan chat.'],
    ['t-teal',   'bi-rocket-takeoff',   'Aktif otomatis',        'Akun hosting dibuat otomatis begitu pembayaran masuk.'],
    ['t-coral',  'bi-window-stack',     'cPanel & installer',    'Pasang WordPress, Laravel, atau Joomla dalam hitungan menit.'],
    ['t-indigo', 'bi-envelope-at',      'Email bisnis',          'Alamat email dengan domain sendiri, siap dipakai di ponsel.'],
    ['t-amber',  'bi-credit-card',      'Bayar mudah',           'Transfer bank, e-wallet, kartu kredit, dan QRIS.'],
  ];
  $steps = [
    ['Pilih domain',        'Cari nama yang cocok dan cek ketersediaannya langsung.'],
    ['Pilih paket hosting', 'Sesuaikan paket dengan kebutuhan website Anda.'],
    ['Bayar & online',      'Akun aktif otomatis setelah pembayaran diterima.'],
  ];
@endphp

<section id="fitur" class="py-5">
  <div class="container">
    <h2 class="text-center mb-5">Kenapa memilih {{ $siteName ?? 'kami' }}</h2>
    <div class="row g-4">
      @foreach ($features as [$tile, $icon, $title, $desc])
        <div class="col-md-6 col-lg-3">
          <div class="feat h-100">
            <span class="tile {{ $tile }}"><i class="bi {{ $icon }}"></i></span>
            <h3 class="h6 mt-2">{{ $title }}</h3>
            <p class="text-body-secondary mb-0">{{ $desc }}</p>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

<section class="py-5 bg-body-tertiary">
  <div class="container">
    <h2 class="text-center mb-5">Mulai dalam 3 langkah</h2>
    <div class="row g-4 text-center">
      @foreach ($steps as [$title, $desc])
        <div class="col-md-4">
          <span class="num num-lg mb-3">{{ $loop->iteration }}</span>
          <h3 class="h6">{{ $title }}</h3>
          <p class="text-body-secondary">{{ $desc }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
