@php
  use App\Models\Setting;
  use App\Services\Cart\CartService;

  $siteName    = Setting::get('site_name', config('app.name', 'NamaHost'));
  $siteLogo    = Setting::get('site_logo');
  $siteIcon    = Setting::get('site_icon');
  $favicon     = Setting::get('site_favicon');
  $tagline     = Setting::get('site_tagline');
  $brandingDisplay = Setting::get('branding_display', 'logo_and_text');
  $footerPages = \App\Models\CmsPage::published()->where('show_in_footer', true)->orderBy('sort_order')->get();
  $navMenus    = \App\Models\NavMenu::active()->whereNull('parent_id')->with(['page', 'children.page', 'defaultChild.page'])->orderBy('sort_order')->get();
  $cartCount   = app(CartService::class)->count();
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
  <link rel="stylesheet" href="{{ asset('assets/css/theme-namahost.css') }}?v={{ @filemtime(public_path('assets/css/theme-namahost.css')) ?: time() }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  {{-- Font Awesome dipakai view bawaan; Bootstrap Icons dipakai desain NamaHost --}}
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body data-theme="namahost" class="lumora-public d-flex flex-column" style="min-height:100vh">

  @if ($isImpersonating)
    <div id="impersonateBar" class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
      <span>
        <i class="bi bi-person-fill-lock"></i>
        <b>{{ session('impersonator_admin_name') }}</b> sedang login sebagai <b>{{ auth('client')->user()->name }}</b>
      </span>
      <form method="POST" action="{{ route('client.impersonate.stop') }}">
        @csrf
        <button type="submit" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:0">Kembali ke Admin</button>
      </form>
    </div>
  @endif

  <header id="publicHeader" style="{{ $isImpersonating ? 'top:41px' : '' }}">
    <nav class="navbar navbar-expand-lg" data-bs-theme="dark">
      <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}">
          @if ($brandingDisplay === 'logo_and_text')
            @if ($siteIcon)
              <img src="{{ route('branding.file', $siteIcon) }}" alt="" style="height:34px;width:34px;object-fit:contain;border-radius:.6rem">
            @elseif ($siteLogo)
              <img src="{{ route('branding.file', $siteLogo) }}" alt="" style="height:34px;width:34px;object-fit:contain;border-radius:.6rem">
            @else
              <span class="brand-mark"><i class="bi bi-hdd-network"></i></span>
            @endif
            <span>{{ $siteName }}</span>
          @elseif ($brandingDisplay === 'logo_only' && $siteLogo)
            <img src="{{ route('branding.file', $siteLogo) }}" alt="{{ $siteName }}" style="height:44px;width:auto;max-width:240px;object-fit:contain">
          @else
            <span>{{ $siteName }}</span>
          @endif
        </a>

        <div class="d-flex align-items-center gap-2 order-lg-last">
          <a href="{{ route('cart.index') }}" class="cart-link" aria-label="Keranjang">
            <i class="bi bi-cart3"></i>
            <span id="cartBadge" class="{{ $cartCount > 0 ? '' : 'd-none' }}">{{ $cartCount }}</span>
          </a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicMenu" aria-controls="publicMenu" aria-expanded="false" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
          </button>
        </div>

        <div class="collapse navbar-collapse" id="publicMenu">
          <ul id="publicHeaderNav" class="navbar-nav me-auto">
            @foreach ($navMenus as $item)
              @php
                $validChildren = $item->direct_child_target ? collect() : $item->children->filter(fn ($c) => $c->resolved_url);
                $isActive = $item->active_pattern && request()->routeIs($item->active_pattern);
              @endphp

              @if ($validChildren->isNotEmpty())
                <li class="nav-item dropdown">
                  <a class="nav-link dropdown-toggle {{ $isActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">{{ $item->label }}</a>
                  <ul class="dropdown-menu">
                    @if ($item->resolved_url)
                      <li><a class="dropdown-item fw-semibold" href="{{ $item->resolved_url }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a></li>
                      <li><hr class="dropdown-divider"></li>
                    @endif
                    @foreach ($validChildren as $child)
                      <li><a class="dropdown-item" href="{{ $child->resolved_url }}" @if ($child->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $child->label }}</a></li>
                    @endforeach
                  </ul>
                </li>
              @elseif ($item->resolved_url)
                <li class="nav-item">
                  <a class="nav-link {{ $isActive ? 'active' : '' }}" href="{{ $item->resolved_url }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>
                </li>
              @endif
            @endforeach
          </ul>

          <div class="nav-actions d-flex align-items-center gap-2 py-2 py-lg-0">
            @auth('client')
              <a href="{{ route('client.dashboard') }}" class="btn btn-accent">Akun Saya</a>
            @else
              <a href="{{ route('client.login') }}" class="btn btn-outline-light">Masuk</a>
              <a href="{{ route('client.register') }}" class="btn btn-accent">Daftar</a>
            @endauth
          </div>
        </div>
      </div>
    </nav>
  </header>

  <main class="flex-grow-1">
    @include('public.partials.toast')

    @hasSection('full-width')
      @yield('full-width')
    @else
      <div class="container py-5">
        @yield('content')
      </div>
    @endif
  </main>

  <footer id="publicFooter">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-5">
          <div class="text-white fw-bold fs-5 mb-2 d-flex align-items-center gap-2">
            <span class="brand-mark"><i class="bi bi-hdd-network"></i></span>{{ $siteName }}
          </div>
          @if ($tagline)
            <p class="mb-0">{{ $tagline }}</p>
          @endif
        </div>
        <div class="col-6 col-md-3">
          <div class="ft-title">Produk</div>
          <ul class="list-unstyled mb-0">
            <li><a href="{{ route('catalog.index') }}">Semua paket</a></li>
            <li><a href="{{ route('domain.search') }}">Cari domain</a></li>
            <li><a href="{{ route('domains.transfer') }}">Transfer domain</a></li>
          </ul>
        </div>
        <div class="col-6 col-md-4">
          <div class="ft-title">Akun &amp; informasi</div>
          <ul class="list-unstyled mb-0">
            <li><a href="{{ route('client.login') }}">Area klien</a></li>
            <li><a href="{{ route('client.register') }}">Daftar</a></li>
            @foreach ($footerPages as $fp)
              <li><a href="{{ route('page.show', $fp->slug) }}">{{ $fp->title }}</a></li>
            @endforeach
          </ul>
        </div>
      </div>
      <hr class="my-4">
      <small>{{ Setting::get('footer_text') ?: '© ' . date('Y') . ' ' . $siteName . '. Seluruh hak dilindungi.' }}</small>
    </div>
  </footer>

  <script src="{{ asset('assets/js/vendor/bootstrap-5.3.8.bundle.min.js') }}"></script>
  @include('public.partials.livechat')

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
