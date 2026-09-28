  {{-- ══════════ Hero (tema Modern) ══════════ --}}
  <section class="mx-hero">
    <div class="mx-container">
      <div class="mx-hero-grid">
        <div>
          <span class="mx-eyebrow">Aktivasi otomatis, langsung online</span>
          <h1 class="mx-h1">{{ $tagline }}</h1>
          <p class="mx-lead">Cek nama domain impianmu, pilih paket hosting, bayar — layanan langsung aktif tanpa menunggu.</p>

          <form method="GET" action="{{ route('domain.search') }}" class="mx-search">
            <i class="fa-solid fa-globe"></i>
            <input type="text" name="domain" value="{{ request('domain') }}" placeholder="ketik nama domain impianmu…" required>
            <button type="submit" class="btn btn-theme" style="padding:.7rem 1.6rem">Cek Domain</button>
          </form>

          <div class="mx-trust">
            <span><i class="fa-solid fa-shield-halved"></i>SSL gratis</span>
            <span><i class="fa-solid fa-bolt"></i>Aktif otomatis</span>
            <span><i class="fa-solid fa-headset"></i>Support responsif</span>
          </div>
        </div>

        @if ($popularTlds->isNotEmpty())
          <aside class="mx-panel">
            <h3>Harga domain populer</h3>
            @foreach ($popularTlds as $tld)
              <div class="mx-tld">
                <b>{{ $tld->extension }}</b>
                <span>Rp {{ number_format($tld->register_price, 0, ',', '.') }}</span>
              </div>
            @endforeach
          </aside>
        @endif
      </div>
    </div>
  </section>
