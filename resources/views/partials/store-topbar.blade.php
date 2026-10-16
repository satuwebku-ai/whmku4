{{--
  Header "layout store" di portal client.

  Dipakai oleh layout client (semua tema) saat halaman menetapkan
  @section('store-mode'): sidebar dashboard diganti header belanja yang
  fokus -- merek, pintasan etalase (Hosting, VPS, Domain, Lisensi), tombol
  kembali ke Dashboard, keranjang, dan akun.

  Variabel dari layout induk: $siteName, $client (null untuk tamu), $cartCount.

  Mengikuti TEMA client yang aktif: tiap tema punya varian sendiri (default =
  gradien indigo gelap, modern = krem translusen, namahost = navy-teal), memakai
  nilai yang sama dengan header/sidebar asli tema itu. Tema buatan sendiri
  memakai varian putih netral dengan aksen --lumora-theme. Ikon Font Awesome.
--}}
@php
  $brandingDisplay = \App\Models\Setting::get('branding_display', 'logo_and_text');
  $siteLogo = \App\Models\Setting::get('site_logo');
  $siteIcon = \App\Models\Setting::get('site_icon');

  // [label, route PUBLIK (diubah StoreUrl bila klien login), ikon, pola route aktif di Store]
  // Klien login: tautan menuju halaman Store (/store/...); tamu: etalase publik, tanpa status aktif.
  $isVpsSection = request()->route('section') === 'vps';
  $storeLinks = [
    ['Store', 'store.index', 'fa-store', ['store.index']],
    ['Hosting', 'catalog.index', 'fa-server', $isVpsSection ? [] : ['store.hosting', 'store.category', 'store.product']],
    ['VPS', 'catalog.vps', 'fa-cloud', $isVpsSection ? ['store.category', 'store.product'] : ['store.vps']],
    ['Domain', 'domain.search', 'fa-globe', ['store.domain', 'store.transfer', 'store.domain-premium']],
    ['Lisensi', 'license.index', 'fa-key', ['store.license', 'store.license.show']],
  ];

  $inCart = request()->routeIs('cart.*', 'client.checkout*');

  // Tampilan mengikuti tema client yang aktif; tema yang tidak dikenal memakai varian netral.
  $themeKey = \App\Support\ThemeRegistry::active('client');
  $variant = in_array($themeKey, ['default', 'modern', 'namahost'], true) ? $themeKey : 'light';

  // Tamu (belum login) belanja di layout yang sama: Dashboard/avatar diganti Masuk dan Daftar.
  $isGuest = ! $client;
@endphp
<style>
  /* Dasar: semua warna dari variabel --st-*; tiap tema client mengisinya sendiri (varian di bawah). */
  .store-topbar { position: sticky; z-index: 1030; background: var(--st-bg); color: var(--st-fg);
    border-bottom: 1px solid var(--st-border); box-shadow: var(--st-shadow); }
  .store-topbar::before { content: ""; position: absolute; inset: 0; pointer-events: none; background-image: var(--st-overlay, none); background-size: 22px 22px; }
  .store-topbar__row { position: relative; display: flex; align-items: center; gap: 1rem; max-width: 80rem; margin: 0 auto; padding: .6rem 1rem; min-height: var(--st-height, 64px); }
  .store-topbar__brand { display: inline-flex; align-items: center; gap: .55rem; text-decoration: none; color: var(--st-fg); font-weight: 700; flex-shrink: 0; }
  .store-topbar__brand:hover { color: var(--st-fg); }
  .store-topbar__nav { display: flex; align-items: center; gap: .25rem; margin-left: .75rem; }
  .store-topbar__link { display: inline-flex; align-items: center; gap: .45rem; padding: .45rem .8rem; border-radius: .6rem; font-size: .85rem; font-weight: 500;
    color: var(--st-muted); text-decoration: none; white-space: nowrap; transition: background .15s, color .15s; }
  .store-topbar__link:hover, .store-topbar__link.is-active { background: var(--st-hover-bg); color: var(--st-hover-fg); }
  .store-topbar__link.is-active { font-weight: 600; }
  .store-topbar__end { display: flex; align-items: center; gap: .5rem; margin-left: auto; }
  .store-topbar__cart { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 2.4rem; height: 2.4rem; border-radius: .7rem;
    color: var(--st-cart-fg); text-decoration: none; background: var(--st-cart-bg); transition: background .15s, color .15s; }
  .store-topbar__cart:hover, .store-topbar__cart.is-active { background: var(--st-cart-hover-bg); color: var(--st-cart-hover-fg); }
  .store-topbar__badge { position: absolute; top: -.3rem; right: -.3rem; min-width: 1.1rem; height: 1.1rem; padding: 0 .25rem; border-radius: 999px;
    background: #f43f5e; color: #fff; font-size: .65rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
  .store-topbar__badge.d-none { display: none !important; }
  .store-topbar__avatar { width: 2.2rem; height: 2.2rem; border-radius: 50%; border: 2px solid var(--st-avatar-border); object-fit: cover; }
  .store-topbar__btn { display: inline-flex; align-items: center; padding: .42rem .95rem; border-radius: .6rem; font-size: .85rem; font-weight: 600; text-decoration: none; white-space: nowrap; transition: opacity .15s; }
  .store-topbar__btn--ghost { color: var(--st-fg); border: 1px solid var(--st-btn-border); }
  .store-topbar__btn--ghost:hover { background: var(--st-hover-bg); color: var(--st-hover-fg); }
  .store-topbar__btn--cta { background: var(--st-cta-bg); color: var(--st-cta-fg); border: 1px solid transparent; }
  .store-topbar__btn--cta:hover { opacity: .9; color: var(--st-cta-fg); }
  .store-topbar__mobile { position: relative; display: flex; gap: .4rem; overflow-x: auto; scrollbar-width: none; padding: 0 1rem .65rem; }
  .store-topbar__mobile::-webkit-scrollbar { display: none; }
  .store-topbar__mobile .store-topbar__link { background: var(--st-chip-bg); }

  /* Default: gradien indigo gelap + pola titik, sama seperti #clientTopbar tema Default. */
  .store-topbar--default { --st-bg: linear-gradient(100deg, #1e1b4b 0%, #312e81 45%, #4f46e5 100%); --st-fg: #fff; --st-muted: rgba(255, 255, 255, .82);
    --st-hover-bg: rgba(255, 255, 255, .14); --st-hover-fg: #fff; --st-border: transparent; --st-shadow: 0 4px 24px -8px rgba(30, 27, 75, .35);
    --st-overlay: radial-gradient(circle at 15% 30%, rgba(255, 255, 255, .08) 1px, transparent 1px);
    --st-cart-bg: rgba(255, 255, 255, .14); --st-cart-fg: #fff; --st-cart-hover-bg: #fff; --st-cart-hover-fg: #4f46e5;
    --st-avatar-border: rgba(255, 255, 255, .3); --st-chip-bg: rgba(255, 255, 255, .12); --st-btn-border: rgba(255, 255, 255, .4);
    --st-cta-bg: #fff; --st-cta-fg: #312e81; --st-height: 68px; }

  /* Modern: krem translusen + blur dan garis bawah tipis, sama seperti #clientTopbar tema Modern. */
  .store-topbar--modern { --st-bg: rgba(250, 249, 246, .85); --st-fg: #18181b; --st-muted: #52525b; --st-hover-bg: #f0efe9; --st-hover-fg: #18181b;
    --st-border: #ece9e2; --st-shadow: none; --st-cart-bg: #f0efe9; --st-cart-fg: #3f3f46;
    --st-cart-hover-bg: var(--lumora-theme, #4f46e5); --st-cart-hover-fg: #fff; --st-avatar-border: #ece9e2; --st-chip-bg: #f0efe9;
    --st-btn-border: #e4e1d8; --st-cta-bg: var(--lumora-theme, #4f46e5); --st-cta-fg: #fff; --st-height: 72px;
    backdrop-filter: saturate(180%) blur(10px); -webkit-backdrop-filter: saturate(180%) blur(10px); }

  /* Namahost: navy-teal seperti sidebar tema Namahost, aksen cyan. */
  .store-topbar--namahost { --st-bg: linear-gradient(100deg, #0b2a33 0%, #0e4250 100%); --st-fg: #fff; --st-muted: #b8ced3;
    --st-hover-bg: rgba(255, 255, 255, .1); --st-hover-fg: #fff; --st-border: transparent; --st-shadow: 0 4px 20px -8px rgba(11, 42, 51, .45);
    --st-cart-bg: rgba(255, 255, 255, .1); --st-cart-fg: #fff; --st-cart-hover-bg: var(--c-cyan, #22d3c5); --st-cart-hover-fg: #0b2a33;
    --st-avatar-border: rgba(255, 255, 255, .35); --st-chip-bg: rgba(255, 255, 255, .1); --st-btn-border: rgba(255, 255, 255, .35);
    --st-cta-bg: var(--c-cyan, #22d3c5); --st-cta-fg: #0b2a33; --st-height: 64px; }

  /* Tema lain (folder tema buatan sendiri): putih netral dengan aksen --lumora-theme. */
  .store-topbar--light { --st-bg: #fff; --st-fg: #0f172a; --st-muted: #475569;
    --st-hover-bg: color-mix(in srgb, var(--lumora-theme, #4f46e5) 10%, #fff); --st-hover-fg: var(--lumora-theme, #4f46e5);
    --st-border: #e2e8f0; --st-shadow: 0 1px 8px rgba(15, 23, 42, .05); --st-cart-bg: #f1f5f9; --st-cart-fg: #475569;
    --st-cart-hover-bg: var(--lumora-theme, #4f46e5); --st-cart-hover-fg: #fff; --st-avatar-border: #e2e8f0; --st-chip-bg: #f1f5f9;
    --st-btn-border: #cbd5e1; --st-cta-bg: var(--lumora-theme, #4f46e5); --st-cta-fg: #fff; --st-height: 64px; }
</style>

<header class="store-topbar store-topbar--{{ $variant }}" style="top:{{ session('impersonator_admin_id') ? '41px' : '0' }}">
  <div class="store-topbar__row">
    <a href="{{ $isGuest ? route('home') : route('client.dashboard') }}" class="store-topbar__brand" title="{{ $isGuest ? 'Ke beranda' : 'Kembali ke Dashboard' }}">
      @if ($brandingDisplay === 'logo_only' && $siteLogo)
        <img src="{{ route('branding.file', $siteLogo) }}" alt="{{ $siteName }}" style="height:34px;width:auto;max-width:180px;object-fit:contain">
      @else
        @if ($siteIcon || $siteLogo)
          <img src="{{ route('branding.file', $siteIcon ?: $siteLogo) }}" alt="" style="height:32px;width:32px;object-fit:contain">
        @else
          <span class="d-inline-flex align-items-center justify-content-center rounded-3 text-white" style="width:32px;height:32px;background:var(--lumora-theme, #4f46e5)">
            <i class="fa-solid fa-bag-shopping" style="font-size:.85rem"></i>
          </span>
        @endif
        <span class="d-none d-sm-inline">{{ $siteName }}</span>
      @endif
    </a>

    <nav class="store-topbar__nav d-none d-md-flex" aria-label="Etalase">
      @foreach ($storeLinks as [$label, $route, $icon, $activeOn])
        @php $linkOn = $activeOn && request()->routeIs(...$activeOn); @endphp
        <a href="{{ \App\Support\StoreUrl::route($route) }}" class="store-topbar__link {{ $linkOn ? 'is-active' : '' }}" @if ($linkOn) aria-current="page" @endif><i class="fa-solid {{ $icon }}"></i> {{ $label }}</a>
      @endforeach
    </nav>

    <div class="store-topbar__end">
      @unless ($isGuest)
        <a href="{{ route('client.dashboard') }}" class="store-topbar__link" title="Kembali ke Dashboard">
          <i class="fa-solid fa-gauge"></i><span class="d-none d-lg-inline">Dashboard</span>
        </a>
      @endunless

      <a href="{{ route('cart.index') }}" class="store-topbar__cart {{ $inCart ? 'is-active' : '' }}" aria-label="Keranjang">
        <i class="fa-solid fa-cart-shopping"></i>
        <span id="cartBadge" class="store-topbar__badge {{ $cartCount > 0 ? '' : 'd-none' }}">{{ $cartCount }}</span>
      </a>

      @if ($isGuest)
        <a href="{{ route('client.login') }}" class="store-topbar__btn store-topbar__btn--ghost">Masuk</a>
        <a href="{{ route('client.register') }}" class="store-topbar__btn store-topbar__btn--cta d-none d-sm-inline-flex">Daftar</a>
      @else
        <img src="{{ $client->avatar_url }}" class="store-topbar__avatar" alt="{{ $client->name }}" title="{{ $client->name }}">
      @endif
    </div>
  </div>

  <nav class="store-topbar__mobile d-md-none" aria-label="Etalase">
    @foreach ($storeLinks as [$label, $route, $icon, $activeOn])
      @php $linkOn = $activeOn && request()->routeIs(...$activeOn); @endphp
        <a href="{{ \App\Support\StoreUrl::route($route) }}" class="store-topbar__link {{ $linkOn ? 'is-active' : '' }}" @if ($linkOn) aria-current="page" @endif><i class="fa-solid {{ $icon }}"></i> {{ $label }}</a>
    @endforeach
  </nav>
</header>
