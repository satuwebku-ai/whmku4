@php
  use App\Models\Setting;
  use App\Services\Cart\CartService;

  $siteName   = Setting::get('site_name', config('app.name', 'Lumora Hosting'));
  $siteLogo   = Setting::get('site_logo');
  $favicon    = Setting::get('site_favicon');
  $themeColor = Setting::get('theme_color', '#6366F1');
  $footerPages = \App\Models\Page::published()->where('show_in_footer', true)->orderBy('sort_order')->get();
  $navMenus = \App\Models\NavMenu::active()->whereNull('parent_id')->with(['page', 'children.page'])->orderBy('sort_order')->get();
  $cartCount = app(CartService::class)->count();
  $isImpersonating = session('impersonator_admin_id') && auth('client')->check();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  @include('public.partials.head')

  @if ($favicon)
    <link rel="icon" href="{{ route('branding.file', $favicon) }}">
  @endif

  <link rel="stylesheet" href="{{ asset('assets/css/vendor/bootstrap-5.3.8.min.css') }}?v={{ @filemtime(public_path('assets/css/vendor/bootstrap-5.3.8.min.css')) ?: time() }}">
  <link rel="stylesheet" href="{{ asset('assets/css/lumora-public.css') }}?v={{ @filemtime(public_path('assets/css/lumora-public.css')) ?: time() }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  {{-- Warna tema dinamis dari Pengaturan -> Umum. File CSS statis tidak
       bisa berisi kode Blade, jadi variabel ini di-set di sini. --}}
  <style>:root{ --lumora-theme: {{ $themeColor }}; }</style>
</head>
<body class="lumora-public d-flex flex-column" style="min-height:100vh">

  @if ($isImpersonating)
    <div id="impersonateBar" class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
      <span>
        <i class="fa-solid fa-user-shield"></i>
        <b>{{ session('impersonator_admin_name') }}</b> sedang login sebagai <b>{{ auth('client')->user()->name }}</b>
      </span>
      <form method="POST" action="{{ route('client.impersonate.stop') }}">
        @csrf
        <button type="submit" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:0">
          Kembali ke Admin
        </button>
      </form>
    </div>
  @endif

  <header id="publicHeader" style="{{ $isImpersonating ? 'top:41px' : '' }}">
    <div class="container d-flex align-items-center justify-content-between" style="height:64px;max-width:72rem">
      <a href="{{ route('home') }}" class="d-flex align-items-center gap-2 text-decoration-none flex-shrink-0">
        @php
          $brandingDisplay = \App\Models\Setting::get('branding_display', 'logo_and_text');
          $siteIcon = \App\Models\Setting::get('site_icon');
        @endphp

        @if ($brandingDisplay === 'logo_and_text')
          {{-- Mode "Logo + Nama": pakai ikon KECIL (bukan logo lengkap
               yang biasanya sudah memuat tulisan nama merek + tagline
               di dalam gambarnya) -- supaya tidak tampil dobel dengan
               teks nama situs di sebelahnya. Jatuh balik ke logo utama
               kalau ikon kecil belum diatur, lalu ke lambang bawaan. --}}
          @if ($siteIcon)
            <img src="{{ route('branding.file', $siteIcon) }}" alt="" style="height:36px;width:36px;object-fit:contain">
          @elseif ($siteLogo)
            <img src="{{ route('branding.file', $siteLogo) }}" alt="" style="height:36px;width:36px;object-fit:contain">
          @else
            <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:{{ $themeColor }}">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="2.2"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
            </span>
          @endif
          <span class="fw-bold text-dark" style="font-size:1.05rem;letter-spacing:-.01em">{{ $siteName }}</span>
        @elseif ($brandingDisplay === 'logo_only' && $siteLogo)
          {{-- Mode "Logo Saja": logo dianggap sudah lengkap (nama +
               tagline di dalamnya), jadi ditampilkan lebih besar supaya
               tetap jelas terbaca, tanpa teks tambahan di sebelahnya. --}}
          <img src="{{ route('branding.file', $siteLogo) }}" alt="{{ $siteName }}" style="height:52px;width:auto;max-width:260px;object-fit:contain">
        @else
          <span class="fw-bold text-dark" style="font-size:1.05rem;letter-spacing:-.01em">{{ $siteName }}</span>
        @endif
      </a>

      <nav id="publicHeaderNav" class="d-flex align-items-center gap-4">
        @foreach ($navMenus as $item)
          @php $validChildren = $item->children->filter(fn ($c) => $c->resolved_url); @endphp

          @if ($validChildren->isNotEmpty())
            <div class="public-menu-item py-2" style="margin:-.5rem 0">
              <button type="button" class="btn btn-link p-0 nav-link d-flex align-items-center gap-1 border-0 {{ $item->active_pattern && request()->routeIs($item->active_pattern) ? 'active' : '' }}">
                {{ $item->label }}
                <i class="fa-solid fa-chevron-down" style="font-size:9px;opacity:.5"></i>
              </button>
              <div class="public-submenu">
                <div class="public-submenu-inner">
                  @if ($item->resolved_url)
                    <a href="{{ $item->resolved_url }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>
                    <div class="border-top my-1"></div>
                  @endif
                  @foreach ($validChildren as $child)
                    <a href="{{ $child->resolved_url }}" @if ($child->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $child->label }}</a>
                  @endforeach
                </div>
              </div>
            </div>
          @elseif ($item->resolved_url)
            <a href="{{ $item->resolved_url }}"
               @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
               class="nav-link text-decoration-none {{ $item->active_pattern && request()->routeIs($item->active_pattern) ? 'active' : '' }}">
              {{ $item->label }}
            </a>
          @endif
        @endforeach
      </nav>

      <div class="d-flex align-items-center gap-3 flex-shrink-0">
        <a href="{{ route('cart.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center position-relative" style="width:36px;height:36px;padding:0;border-color:transparent">
          <i class="fa-solid fa-cart-shopping" style="font-size:14px"></i>
          <span id="cartBadge" class="{{ $cartCount > 0 ? '' : 'd-none' }}">{{ $cartCount }}</span>
        </a>
        @auth('client')
          <a href="{{ route('client.dashboard') }}" class="btn btn-outline-secondary btn-sm">Akun Saya</a>
        @else
          <a href="{{ route('client.login') }}" class="btn btn-outline-secondary btn-sm d-none d-sm-inline-flex">Masuk</a>
          <a href="{{ route('client.register') }}" class="btn btn-theme btn-sm">Daftar</a>
        @endauth
      </div>
    </div>

    {{-- Nav mobile --}}
    <nav id="publicMobileNav" class="align-items-center gap-3 px-3 pb-3 small text-muted" style="overflow-x:auto;display:none">
      @foreach ($navMenus as $item)
        @if ($item->resolved_url)
          <a href="{{ $item->resolved_url }}"
             @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
             class="text-nowrap text-decoration-none {{ $item->active_pattern && request()->routeIs($item->active_pattern) ? 'text-theme fw-medium' : 'text-muted' }}">
            {{ $item->label }}
          </a>
        @endif
        @foreach ($item->children as $child)
          @continue(! $child->resolved_url)
          <a href="{{ $child->resolved_url }}"
             @if ($child->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
             class="text-nowrap text-decoration-none {{ $child->active_pattern && request()->routeIs($child->active_pattern) ? 'text-theme fw-medium' : 'text-muted' }}">
            <i class="fa-solid fa-arrow-turn-up fa-rotate-90" style="font-size:9px"></i> {{ $child->label }}
          </a>
        @endforeach
      @endforeach
    </nav>
  </header>

  <main class="flex-grow-1">
    <x-toast />

    @hasSection('full-width')
      @yield('full-width')
    @else
      <div class="container py-5" style="max-width:72rem">
        @yield('content')
      </div>
    @endif
  </main>

  <footer id="publicFooter">
    <div class="container" style="max-width:72rem">
      @if ($footerPages->isNotEmpty())
        <nav class="d-flex flex-wrap gap-3 mb-3">
          @foreach ($footerPages as $fp)
            <a href="{{ route('page.show', $fp->slug) }}">{{ $fp->title }}</a>
          @endforeach
        </nav>
      @endif
      <p class="text-muted mb-0" style="font-size:14px">{{ Setting::get('footer_text') ?: '© ' . date('Y') . ' ' . $siteName . '. Semua hak dilindungi.' }}</p>
    </div>
  </footer>

  <script src="{{ asset('assets/js/vendor/bootstrap-5.3.8.bundle.min.js') }}"></script>
  @include('public.partials.livechat')

  {{-- ══════════ Modal konfirmasi ══════════
       Sebelumnya atribut data-confirm di berbagai tombol (mis. "Kosongkan
       Keranjang") tidak pernah punya JS penanganannya sama sekali di
       situs publik -- tombolnya submit langsung tanpa konfirmasi.
       Dilengkapi di sini, pola sama persis dengan yang sudah jalan di
       panel admin. --}}
  <div class="modal" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 overflow-hidden">
        <div class="p-4">
          <div class="d-flex align-items-start gap-3">
            <span id="confirmIcon" class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 bg-danger bg-opacity-10 text-danger" style="width:44px;height:44px">
              <i class="fa-solid fa-triangle-exclamation"></i>
            </span>
            <div class="flex-grow-1 min-w-0">
              <h3 id="confirmTitle" class="h6 fw-bold text-dark mb-1">Konfirmasi</h3>
              <p id="confirmText" class="small text-muted mb-0" style="line-height:1.6"></p>
            </div>
          </div>
        </div>
        <div class="px-4 py-3 bg-light border-top d-flex align-items-center justify-content-end gap-2">
          <button type="button" id="confirmCancel" class="btn btn-outline-secondary">Batal</button>
          <button type="button" id="confirmOk" class="btn btn-danger">Lanjutkan</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const modal  = document.getElementById('confirmModal');
      const icon   = document.getElementById('confirmIcon');
      const title  = document.getElementById('confirmTitle');
      const text   = document.getElementById('confirmText');
      const okBtn  = document.getElementById('confirmOk');
      const noBtn  = document.getElementById('confirmCancel');

      let pendingForm = null;
      let confirmBackdrop = null;

      const styles = {
        danger: { cls: 'bg-danger bg-opacity-10 text-danger', icon: 'fa-triangle-exclamation', btn: 'btn btn-danger',  label: 'Ya, Lanjutkan' },
        warn:   { cls: 'bg-warning bg-opacity-10 text-warning', icon: 'fa-circle-exclamation', btn: 'btn btn-primary', label: 'Lanjutkan' },
        info:   { cls: 'bg-primary bg-opacity-10 text-primary', icon: 'fa-circle-info',        btn: 'btn btn-primary', label: 'Lanjutkan' },
      };

      function openModal() {
        modal.classList.add('show');
        modal.style.display = 'block';
        document.body.classList.add('modal-open');

        confirmBackdrop = document.createElement('div');
        confirmBackdrop.className = 'modal-backdrop';
        document.body.appendChild(confirmBackdrop);
        requestAnimationFrame(() => confirmBackdrop.classList.add('show'));

        okBtn.focus();
      }
      function closeModal() {
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');

        if (confirmBackdrop) {
          confirmBackdrop.classList.remove('show');
          confirmBackdrop.remove();
          confirmBackdrop = null;
        }
        pendingForm = null;
      }

      function open(form) {
        pendingForm = form;
        const style = styles[form.dataset.confirmStyle || 'danger'] || styles.danger;

        icon.className = 'rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ' + style.cls;
        icon.style.width = '44px'; icon.style.height = '44px';
        icon.innerHTML = '<i class="fa-solid ' + style.icon + '"></i>';
        okBtn.className = style.btn;
        okBtn.textContent = form.dataset.confirmLabel || style.label;

        title.textContent = form.dataset.confirmTitle || 'Konfirmasi';
        text.textContent  = form.dataset.confirm;

        openModal();
      }

      document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form.dataset && form.dataset.confirm && !form.dataset.confirmed) {
          e.preventDefault();
          open(form);
        }
      });

      okBtn.addEventListener('click', function () {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = '1';
        pendingForm.submit();
        closeModal();
      });

      noBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('show')) closeModal(); });
    })();
  </script>
</body>
</html>
