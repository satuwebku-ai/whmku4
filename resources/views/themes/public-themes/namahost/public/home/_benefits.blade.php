{{-- ══════════ Keunggulan + 3 langkah (tema NamaHost) ══════════ --}}
@php
  // Teks bisa diedit di CMS > Halaman Depan; warna & ikon mengikuti urutan.
  $features = \App\Support\HomeContent::benefits([
    ['tile' => 't-teal', 'icon' => 'bi-lightning-charge', 'title' => 'Server cepat', 'desc' => 'LiteSpeed dan NVMe membuat website terbuka di bawah 1 detik.'],
    ['tile' => 't-coral', 'icon' => 'bi-shield-check', 'title' => 'Aman', 'desc' => 'Sertifikat SSL tersedia, firewall, dan pemindai malware otomatis.'],
    ['tile' => 't-indigo', 'icon' => 'bi-arrow-repeat', 'title' => 'Backup otomatis', 'desc' => 'Pulihkan file dan database sendiri dari area klien.'],
    ['tile' => 't-amber', 'icon' => 'bi-headset', 'title' => 'Bantuan ramah', 'desc' => 'Tim support siap membantu lewat tiket dan chat.'],
    ['tile' => 't-teal', 'icon' => 'bi-rocket-takeoff', 'title' => 'Aktif otomatis', 'desc' => 'Akun hosting dibuat otomatis begitu pembayaran masuk.'],
    ['tile' => 't-coral', 'icon' => 'bi-window-stack', 'title' => 'cPanel & installer', 'desc' => 'Pasang WordPress, Laravel, atau Joomla dalam hitungan menit.'],
    ['tile' => 't-indigo', 'icon' => 'bi-envelope-at', 'title' => 'Email bisnis', 'desc' => 'Alamat email dengan domain sendiri, siap dipakai di ponsel.'],
    ['tile' => 't-amber', 'icon' => 'bi-credit-card', 'title' => 'Bayar mudah', 'desc' => 'Transfer bank, e-wallet, kartu kredit, dan QRIS.'],
  ]);
  // Langkah juga bisa diedit di CMS > Halaman Depan.
  $steps = \App\Support\HomeContent::steps(\App\Support\HomeContent::DEFAULT_STEPS);
@endphp

<section id="fitur" class="py-5">
  <div class="container">
    <h2 class="text-center mb-5">Kenapa memilih {{ $siteName ?? 'kami' }}</h2>
    <div class="row g-4">
      @foreach ($features as $f)
        <div class="col-md-6 col-lg-3">
          <div class="feat h-100">
            <span class="tile {{ $f['tile'] }}"><i class="bi {{ $f['icon'] }}"></i></span>
            <h3 class="h6 mt-2">{{ $f['title'] }}</h3>
            <p class="text-body-secondary mb-0">{{ $f['desc'] }}</p>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

<section class="py-5 bg-body-tertiary">
  <div class="container">
    <h2 class="text-center mb-5">Mulai dalam {{ count($steps) }} langkah</h2>
    <div class="row g-4 text-center">
      @foreach ($steps as $step)
        <div class="col-md-{{ count($steps) >= 4 ? 3 : 4 }}">
          <span class="num num-lg mb-3">{{ $loop->iteration }}</span>
          <h3 class="h6">{{ $step['title'] }}</h3>
          <p class="text-body-secondary">{{ $step['desc'] }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
