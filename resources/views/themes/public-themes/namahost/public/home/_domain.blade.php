{{-- ══════════ Hero + strip angka + pencarian domain (tema NamaHost) ══════════ --}}
<header class="hero py-5">
  <div class="container py-lg-5">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <h1 class="display-4 mb-3">{{ $tagline }}</h1>
        <p class="lead mb-4">Hosting SSD NVMe, SSL gratis, dan aktivasi otomatis. Cek nama domain Anda sekarang.</p>

        <form method="GET" action="{{ route('domain.search') }}" class="domain-box d-flex flex-wrap flex-sm-nowrap gap-2" role="search">
          <label for="heroDomain" class="visually-hidden">Nama domain</label>
          <input id="heroDomain" name="domain" value="{{ request('domain') }}" class="form-control form-control-lg" placeholder="contoh: tokosaya" autocomplete="off" required>
          <button type="submit" class="btn btn-primary btn-lg px-4">Cek domain</button>
        </form>

        @if ($popularTlds->isNotEmpty())
          <p class="small mt-3 mb-3 text-white-50">
            @foreach ($popularTlds as $tld)
              {{ $tld->extension }} Rp{{ number_format($tld->register_price, 0, ',', '.') }}/thn @if (! $loop->last) &nbsp;|&nbsp; @endif
            @endforeach
          </p>
        @endif

        <ul class="list-inline mb-0 mt-3">
          <li class="list-inline-item me-3"><i class="bi bi-check-circle-fill text-warning me-1"></i>SSL gratis</li>
          <li class="list-inline-item me-3"><i class="bi bi-check-circle-fill text-warning me-1"></i>Aktif otomatis</li>
          <li class="list-inline-item"><i class="bi bi-check-circle-fill text-warning me-1"></i>Dukungan responsif</li>
        </ul>
      </div>

      <div class="col-lg-5 d-none d-lg-block">
        <div class="hero-art">
          <svg viewBox="0 0 400 320" class="w-100" role="img" aria-label="Ilustrasi server hosting">
            <defs><linearGradient id="nhHeroG" x1="0" x2="1"><stop offset="0" stop-color="#22d3c5"/><stop offset="1" stop-color="#5b5bd6"/></linearGradient></defs>
            <circle cx="200" cy="160" r="145" fill="url(#nhHeroG)" opacity=".2"/>
            <ellipse cx="200" cy="160" rx="185" ry="62" fill="none" stroke="#22d3c5" stroke-opacity=".5" stroke-dasharray="4 8"/>
            @foreach ([50, 130, 210] as $y)
              <rect x="90" y="{{ $y }}" width="220" height="64" rx="14" fill="#123f4b" stroke="#22d3c5" stroke-opacity=".6"/>
              <circle class="led" cx="118" cy="{{ $y + 32 }}" r="5" fill="#22d3c5"/>
              <circle class="led" cx="138" cy="{{ $y + 32 }}" r="5" fill="#f5a524"/>
              <circle class="led" cx="158" cy="{{ $y + 32 }}" r="5" fill="#ff6f59"/>
              <rect x="190" y="{{ $y + 22 }}" width="96" height="8" rx="4" fill="#22d3c5" opacity=".55"/>
              <rect x="190" y="{{ $y + 38 }}" width="60" height="8" rx="4" fill="#fff" opacity=".25"/>
            @endforeach
          </svg>
          <span class="chip c1"><i class="bi bi-shield-lock-fill text-success me-1"></i>SSL aktif</span>
          <span class="chip c2"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Website terbuka &lt;1 detik</span>
        </div>
      </div>
    </div>
  </div>
</header>

{{-- Strip angka --}}
<section class="container trust">
  <div class="box row g-3 text-center py-4 mx-0">
    <div class="col-6 col-md-3"><div class="fs-3 fw-bold text-primary" data-count="99.9" data-dec="1" data-suf="%">99,9%</div><small class="text-body-secondary">Uptime server</small></div>
    <div class="col-6 col-md-3"><div class="fs-3 fw-bold text-primary">NVMe</div><small class="text-body-secondary">Penyimpanan SSD</small></div>
    <div class="col-6 col-md-3"><div class="fs-3 fw-bold text-primary">24/7</div><small class="text-body-secondary">Tiket &amp; live chat</small></div>
    <div class="col-6 col-md-3"><div class="fs-3 fw-bold text-primary" data-count="30" data-suf=" hari">30 hari</div><small class="text-body-secondary">Garansi uang kembali</small></div>
  </div>
</section>

{{-- Harga domain (hanya tampil bila ada TLD populer) --}}
@if ($popularTlds->isNotEmpty())
  <section id="domain-section" class="py-5">
    <div class="container">
      <div class="text-center mb-4">
        <span class="badge text-bg-warning mb-2">Domain</span>
        <h2>Cari nama domain untuk website Anda</h2>
        <p class="text-body-secondary">Ketik nama bisnis Anda, kami cek ketersediaannya di semua ekstensi populer.</p>
      </div>

      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card h-100"><div class="card-body p-0">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead><tr><th class="ps-4">Ekstensi</th><th class="text-end pe-4">Harga / tahun</th></tr></thead>
                <tbody>
                  @foreach ($popularTlds as $tld)
                    <tr>
                      <td class="ps-4 fw-bold">{{ $tld->extension }}</td>
                      <td class="text-end pe-4">Rp {{ number_format($tld->register_price, 0, ',', '.') }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div></div>
        </div>
        <div class="col-lg-5">
          <div class="d-grid gap-3 h-100">
            <div class="feat d-flex gap-3"><span class="tile t-teal"><i class="bi bi-eye-slash"></i></span><div><h3 class="h6 mb-1">Privasi WHOIS</h3><p class="text-body-secondary small mb-0">Sembunyikan data pribadi dari publik.</p></div></div>
            <div class="feat d-flex gap-3"><span class="tile t-indigo"><i class="bi bi-diagram-3"></i></span><div><h3 class="h6 mb-1">Kelola DNS mudah</h3><p class="text-body-secondary small mb-0">Atur A, CNAME, MX, dan TXT dari area klien.</p></div></div>
            <div class="feat d-flex gap-3"><span class="tile t-amber"><i class="bi bi-arrow-left-right"></i></span><div><h3 class="h6 mb-1">Transfer domain</h3><p class="text-body-secondary small mb-0">Pindahkan domain lama tanpa downtime.</p></div></div>
          </div>
        </div>
      </div>

      <div class="text-center mt-4">
        <a href="{{ route('domain.search') }}" class="btn btn-primary">Buka halaman domain lengkap</a>
        <a href="{{ route('domains.transfer') }}" class="btn btn-outline-primary ms-1">Transfer domain</a>
      </div>
    </div>
  </section>
@endif

<script>
  (function () {
    var els = document.querySelectorAll('[data-count]');
    if (!('IntersectionObserver' in window) || !els.length) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        io.unobserve(e.target);
        var el = e.target, end = +el.dataset.count, dec = +(el.dataset.dec || 0), t0;
        function step(t) {
          if (t0 === undefined) t0 = t;
          var p = Math.min((t - t0) / 1200, 1);
          el.textContent = (end * p).toLocaleString('id-ID', {minimumFractionDigits: dec, maximumFractionDigits: dec}) + (el.dataset.suf || '');
          if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
      });
    });
    els.forEach(function (el) { io.observe(el); });
  })();
</script>
