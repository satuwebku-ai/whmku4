{{--
  Header halaman belanja di portal client: judul, keterangan singkat, pintasan
  ke etalase, dan langkah pemesanan.

  Parameter:
    $title     string       judul halaman
    $subtitle  string|null  keterangan di bawah judul
    $step      int|null     langkah aktif (1 Keranjang, 2 Checkout, 3 Pembayaran); null = tanpa stepper
    $chips     bool         tampilkan pintasan ke etalase (Hosting, VPS, Domain, Lisensi)
    $eyebrow   string|null  teks kecil di atas judul (bawaan "Toko")
    $eyebrowIcon string|null  kelas ikon Font Awesome untuk eyebrow (bawaan fa-bag-shopping)

  Tidak terikat tema: warna mengikuti --lumora-theme milik tema aktif, ikon
  memakai Font Awesome (dimuat ketiga layout client).
--}}
<style>
  .store-hero { position: relative; overflow: hidden; border-radius: 1.25rem; color: #fff; padding: 1.5rem;
    background: var(--lumora-theme, #4f46e5);
    background: linear-gradient(135deg, var(--lumora-theme, #4f46e5) 0%, color-mix(in srgb, var(--lumora-theme, #4f46e5) 55%, #0f172a) 100%);
    box-shadow: 0 12px 30px -14px rgba(15, 23, 42, .45); }
  @media (min-width: 992px) { .store-hero { padding: 2rem 2.25rem; } }
  .store-hero::before, .store-hero::after { content: ""; position: absolute; border-radius: 50%; pointer-events: none; background: rgba(255, 255, 255, .08); }
  .store-hero::before { width: 18rem; height: 18rem; right: -5rem; top: -8rem; }
  .store-hero::after { width: 11rem; height: 11rem; right: 8rem; bottom: -7rem; background: rgba(255, 255, 255, .06); }
  .store-hero > * { position: relative; z-index: 1; }
  .store-hero__eyebrow { font-size: .72rem; letter-spacing: .09em; text-transform: uppercase; font-weight: 600; opacity: .8; }
  .store-hero__title { font-weight: 700; font-size: clamp(1.35rem, 2.4vw, 1.85rem); line-height: 1.2; margin: .3rem 0 .35rem; color: #fff; }
  .store-hero__sub { margin: 0; font-size: .9rem; opacity: .88; max-width: 40rem; }
  .store-chips { display: flex; gap: .5rem; overflow-x: auto; scrollbar-width: none; padding-bottom: .15rem; }
  .store-chips::-webkit-scrollbar { display: none; }
  .store-chip { display: inline-flex; align-items: center; gap: .4rem; white-space: nowrap; padding: .42rem .85rem; border-radius: 999px;
    font-size: .8rem; font-weight: 500; color: #fff; text-decoration: none; background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .2); transition: background .15s, transform .15s; }
  .store-chip:hover { background: rgba(255, 255, 255, .27); color: #fff; transform: translateY(-1px); }
  .store-steps { display: flex; align-items: center; gap: .5rem; list-style: none; margin: 0; padding: .15rem 0; overflow-x: auto; scrollbar-width: none; }
  .store-steps::-webkit-scrollbar { display: none; }
  .store-step { display: inline-flex; align-items: center; gap: .5rem; white-space: nowrap; font-size: .82rem; font-weight: 500;
    color: rgba(255, 255, 255, .72); text-decoration: none; }
  a.store-step:hover { color: #fff; }
  .store-step__dot { width: 1.75rem; height: 1.75rem; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
    font-size: .75rem; font-weight: 700; border: 2px solid rgba(255, 255, 255, .5); }
  .store-step.is-done .store-step__dot { background: #fff; border-color: #fff; color: var(--lumora-theme, #4f46e5); }
  .store-step.is-active { color: #fff; font-weight: 600; }
  .store-step.is-active .store-step__dot { background: #fff; border-color: #fff; color: var(--lumora-theme, #4f46e5); box-shadow: 0 0 0 4px rgba(255, 255, 255, .25); }
  .store-step__line { flex: 0 0 2.25rem; height: 2px; border-radius: 2px; background: rgba(255, 255, 255, .35); }
  .store-step__line.is-done { background: #fff; }
</style>

<section class="store-hero mb-4">
  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
    <div class="min-w-0">
      <div class="store-hero__eyebrow"><i class="fa-solid {{ $eyebrowIcon ?? 'fa-bag-shopping' }} me-1"></i> {{ $eyebrow ?? 'Toko' }}</div>
      <h1 class="store-hero__title">{{ $title }}</h1>
      @isset($subtitle)
        <p class="store-hero__sub">{{ $subtitle }}</p>
      @endisset
    </div>

    @if (! empty($chips))
      <div class="store-chips" style="max-width:100%">
        <a href="{{ \App\Support\StoreUrl::route('catalog.index') }}" class="store-chip"><i class="fa-solid fa-server"></i> Hosting</a>
        <a href="{{ \App\Support\StoreUrl::route('catalog.vps') }}" class="store-chip"><i class="fa-solid fa-cloud"></i> VPS</a>
        <a href="{{ \App\Support\StoreUrl::route('domain.search') }}" class="store-chip"><i class="fa-solid fa-globe"></i> Domain</a>
        <a href="{{ \App\Support\StoreUrl::route('license.index') }}" class="store-chip"><i class="fa-solid fa-key"></i> Lisensi</a>
      </div>
    @endif
  </div>

  @isset($step)
    <div class="mt-4">
      @include('partials.store-steps', ['step' => $step])
    </div>
  @endisset
</section>
