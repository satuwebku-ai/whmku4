@php
  use App\Models\Setting;
  use App\Services\Cart\CartService;
  $siteName = Setting::get('site_name', config('app.name', 'NamaHost'));
  $favicon  = Setting::get('site_favicon');
  $client   = auth('client')->user();
  $cartCount = app(CartService::class)->count();
  $brandingDisplay = Setting::get('branding_display', 'logo_and_text');
  $siteLogo = Setting::get('site_logo');
  $siteIcon = Setting::get('site_icon');
  $isImpersonating = (bool) session('impersonator_admin_id');

  $menu = [
    ['label' => 'Dashboard',          'route' => 'client.dashboard',        'match' => 'client.dashboard*', 'icon' => 'bi-speedometer2'],
    ['label' => 'Pesan Layanan Baru', 'route' => 'catalog.index',           'match' => 'catalog.*',         'icon' => 'bi-cart-plus'],
    ['label' => 'Keranjang',          'route' => 'cart.index',              'match' => 'cart.*',            'icon' => 'bi-cart3', 'badge' => $cartCount],
    ['label' => 'Layanan Saya',       'route' => 'client.services',         'match' => 'client.services*',  'icon' => 'bi-server'],
    ['label' => 'VPS Saya',           'route' => 'client.vps',              'match' => 'client.vps*',       'icon' => 'bi-hdd-rack'],
    ['label' => 'Domain Saya',        'route' => 'client.domains',          'match' => 'client.domains*',   'icon' => 'bi-globe2'],
    ['label' => 'Billing',            'route' => 'client.billing',          'match' => 'client.billing',    'icon' => 'bi-receipt'],
    ['label' => 'Invoice',            'route' => 'client.invoices',         'match' => 'client.invoices*',  'icon' => 'bi-file-earmark-text'],
    ['label' => 'Saldo Saya',         'route' => 'client.balance',          'match' => 'client.balance*',   'icon' => 'bi-wallet2'],
    ['label' => 'Tiket Support',      'route' => 'client.tickets',          'match' => 'client.tickets*',   'icon' => 'bi-life-preserver'],
    ['label' => 'Affiliate',          'route' => 'client.affiliate.index',   'match' => 'client.affiliate*', 'icon' => 'bi-share'],
    ['label' => 'Profil Saya',        'route' => 'client.profile',          'match' => 'client.profile*',   'icon' => 'bi-person-gear'],
  ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') — {{ $siteName }}</title>

@if ($favicon)
  <link rel="icon" href="{{ route('branding.file', $favicon) }}">
@endif

<link rel="stylesheet" href="{{ asset('assets/css/vendor/bootstrap-5.3.8.min.css') }}?v={{ @filemtime(public_path('assets/css/vendor/bootstrap-5.3.8.min.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/theme-namahost.css') }}?v={{ @filemtime(public_path('assets/css/theme-namahost.css')) ?: time() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body data-theme="namahost" class="lumora-public">

  {{-- Pita peringatan impersonasi --}}
  @if ($isImpersonating)
    <div id="impersonateBar" class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
      <span>
        <i class="bi bi-person-fill-lock"></i>
        <b>{{ session('impersonator_admin_name') }}</b> sedang login sebagai <b>{{ $client->name }}</b>
      </span>
      <form method="POST" action="{{ route('client.impersonate.stop') }}">
        @csrf
        <button type="submit" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:0">Kembali ke Admin</button>
      </form>
    </div>
  @endif

  <div class="d-lg-flex">

    {{-- Sidebar --}}
    <aside class="sidebar p-3 flex-shrink-0" data-bs-theme="dark" style="{{ $isImpersonating ? '--imp:41px' : '' }}">
      <div class="d-flex justify-content-between align-items-center mb-3 mb-lg-4">
        <a href="{{ route('client.dashboard') }}" class="navbar-brand text-white text-decoration-none d-flex align-items-center gap-2">
          @if ($brandingDisplay === 'logo_and_text')
            @if ($siteIcon)
              <img src="{{ route('branding.file', $siteIcon) }}" alt="" style="height:32px;width:32px;object-fit:contain;border-radius:.55rem">
            @elseif ($siteLogo)
              <img src="{{ route('branding.file', $siteLogo) }}" alt="" style="height:32px;width:32px;object-fit:contain;border-radius:.55rem">
            @else
              <span class="brand-mark"><i class="bi bi-hdd-network"></i></span>
            @endif
            <span>{{ $siteName }}</span>
          @elseif ($brandingDisplay === 'logo_only' && $siteLogo)
            <img src="{{ route('branding.file', $siteLogo) }}" alt="{{ $siteName }}" style="height:38px;width:auto;max-width:170px;object-fit:contain">
          @else
            <span>{{ $siteName }}</span>
          @endif
        </a>
        <button class="btn btn-outline-light d-lg-none" data-bs-toggle="collapse" data-bs-target="#side" aria-label="Menu"><i class="bi bi-list"></i></button>
      </div>

      <nav id="side" class="collapse d-lg-block">
        <div class="user-chip">
          <img src="{{ $client->avatar_url }}" alt="">
          <div class="min-w-0">
            <div class="text-white fw-bold text-truncate" style="font-size:13.5px">{{ $client->name }}</div>
            <small class="text-white-50">Akun aktif</small>
          </div>
        </div>

        <ul class="nav nav-pills flex-column gap-1">
          @foreach ($menu as $item)
            @php $on = request()->routeIs($item['match']); @endphp
            <li>
              <a class="nav-link d-flex align-items-center {{ $on ? 'active' : '' }}" href="{{ route($item['route']) }}" @if ($on) aria-current="page" @endif>
                <i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}
                @if (($item['badge'] ?? 0) > 0)
                  <span class="badge text-bg-warning ms-auto">{{ $item['badge'] }}</span>
                @endif
              </a>
            </li>
          @endforeach
          <li>
            <form method="POST" action="{{ route('client.logout') }}">
              @csrf
              <button type="submit" class="nav-link w-100 text-start border-0 bg-transparent">
                <i class="bi bi-box-arrow-left me-2"></i>Keluar
              </button>
            </form>
          </li>
        </ul>
      </nav>
    </aside>

    {{-- Konten --}}
    <main class="flex-grow-1 p-3 p-lg-4 min-w-0">
      @include('client.partials.toast')

      @yield('content')
    </main>
  </div>

  {{-- Modal konfirmasi --}}
  <div class="modal" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 overflow-hidden">
        <div class="p-4">
          <div class="d-flex align-items-start gap-3">
            <span id="confirmIcon" class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 bg-warning bg-opacity-10 text-warning" style="width:44px;height:44px">
              <i class="fa-solid fa-circle-exclamation"></i>
            </span>
            <div class="flex-grow-1 min-w-0">
              <h3 id="confirmTitle" class="h6 fw-bold text-dark mb-1">Konfirmasi</h3>
              <p id="confirmText" class="small text-muted mb-0" style="line-height:1.6"></p>
            </div>
          </div>
        </div>
        <div class="px-4 py-3 bg-light border-top d-flex align-items-center justify-content-end gap-2">
          <button type="button" id="confirmCancel" class="btn btn-outline-secondary">Batal</button>
          <button type="button" id="confirmOk" class="btn btn-primary">Lanjutkan</button>
        </div>
      </div>
    </div>
  </div>

  <script src="{{ asset('assets/js/vendor/bootstrap-5.3.8.bundle.min.js') }}"></script>

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
        const style = styles[form.dataset.confirmStyle || 'warn'] || styles.warn;

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

  @include('public.partials.livechat')
</body>
</html>
