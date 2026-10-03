<?php
  use App\Models\Setting;
  use App\Services\Cart\CartService;
  $siteName = Setting::get('site_name', config('app.name', 'Lumora Hosting'));
  $favicon = Setting::get('site_favicon');
  $themeColor = Setting::get('theme_color', '#6366F1');
  $client = auth('client')->user();
  $cartCount = app(CartService::class)->count();

  $menu = [
    ['label' => 'Dashboard', 'route' => 'client.dashboard', 'match' => 'client.dashboard*', 'icon' => 'fa-gauge'],
    ['label' => 'Pesan Layanan Baru', 'route' => 'catalog.index', 'match' => 'catalog.*', 'icon' => 'fa-cart-plus'],
    ['label' => 'Keranjang', 'route' => 'cart.index', 'match' => 'cart.*', 'icon' => 'fa-cart-shopping'],
    // TODO: alihkan ke .bootstrap-preview begitu masing-masing modul selesai dimigrasikan.
    ['label' => 'Layanan Saya', 'route' => 'client.services', 'match' => 'client.services*', 'icon' => 'fa-server'],
    ['label' => 'VPS Saya', 'route' => 'client.vps', 'match' => 'client.vps*', 'icon' => 'fa-cloud'],
    ['label' => 'Domain Saya', 'route' => 'client.domains', 'match' => 'client.domains*', 'icon' => 'fa-globe'],
    ['label' => 'Billing', 'route' => 'client.billing', 'match' => 'client.billing', 'icon' => 'fa-receipt'],
    ['label' => 'Invoice', 'route' => 'client.invoices', 'match' => 'client.invoices*', 'icon' => 'fa-file-invoice'],
    ['label' => 'Lisensi Saya', 'route' => 'client.licenses', 'match' => 'client.licenses*', 'icon' => 'fa-key'],
    ['label' => 'Saldo Saya', 'route' => 'client.balance', 'match' => 'client.balance*', 'icon' => 'fa-wallet'],
    ['label' => 'Tiket Support', 'route' => 'client.tickets', 'match' => 'client.tickets*', 'icon' => 'fa-comments'],
    ['label' => 'Affiliate', 'route' => 'client.affiliate.index', 'match' => 'client.affiliate*', 'icon' => 'fa-share-nodes'],
    ['label' => 'Profil Saya', 'route' => 'client.profile', 'match' => 'client.profile*', 'icon' => 'fa-user'],
  ];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
<title><?php echo $__env->yieldContent('title', 'Dashboard'); ?> — <?php echo e($siteName); ?></title>

<?php if($favicon): ?>
  <link rel="icon" href="<?php echo e(route('branding.file', $favicon)); ?>">
<?php endif; ?>

<link rel="stylesheet" href="<?php echo e(asset('assets/css/vendor/bootstrap-5.3.8.min.css')); ?>?v=<?php echo e(@filemtime(public_path('assets/css/vendor/bootstrap-5.3.8.min.css')) ?: time()); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/css/lumora-public.css')); ?>?v=<?php echo e(@filemtime(public_path('assets/css/lumora-public.css')) ?: time()); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
  :root{ --lumora-theme: <?php echo e($themeColor); ?>; }
  body{ background:#f7f8fb; }

  #clientTopbar{
    position:relative;
    background:linear-gradient(100deg,#1e1b4b 0%,#312e81 45%,#4f46e5 100%);
    height:68px;
    box-shadow:0 4px 24px -8px rgba(30,27,75,.35);
  }
  #clientTopbar::before{
    content:''; position:absolute; inset:0; pointer-events:none;
    background-image:radial-gradient(circle at 15% 30%, rgba(255,255,255,.08) 1px, transparent 1px);
    background-size:22px 22px;
  }

  /* ── Sidebar ── */
  .sidebar-card{
    background:linear-gradient(160deg,#eef1ff 0%,#ffffff 65%);
    border:1px solid #e6e9f7;
    border-radius:1rem;
    padding:1rem;
    margin-bottom:1.25rem;
    box-shadow:0 1px 2px rgba(15,23,42,.03);
  }
  .sidebar-card .avatar-ring{
    width:44px;height:44px;border-radius:50%;padding:2px;
    background:linear-gradient(135deg,var(--lumora-theme),#818cf8);
    flex-shrink:0;
    box-shadow:0 3px 8px -2px rgba(79,70,229,.4);
  }
  .sidebar-card .avatar-ring img{
    width:100%;height:100%;border-radius:50%;object-fit:cover;border:2px solid #fff;display:block;
  }
  .sidebar-status{
    display:inline-flex;align-items:center;gap:.35rem;font-size:10.5px;font-weight:600;
    color:#15803d;background:rgba(21,128,61,.1);padding:.15rem .5rem;border-radius:999px;
  }
  .sidebar-status .pulse-dot{
    width:6px;height:6px;border-radius:50%;background:#22c55e;position:relative;flex-shrink:0;
  }
  .sidebar-status .pulse-dot::after{
    content:''; position:absolute; inset:-3px; border-radius:50%; background:#22c55e;
    opacity:.5; animation:pulseDot 2s ease-out infinite;
  }
  @keyframes pulseDot{
    0%{ transform:scale(.6); opacity:.6; }
    100%{ transform:scale(2.2); opacity:0; }
  }

  .cnav{
    display:flex; align-items:center; gap:.7rem; padding:.55rem .7rem; border-radius:.65rem;
    font-size:13.5px; color:#475569; text-decoration:none; font-weight:500;
    border-left:3px solid transparent; transition:background .15s, color .15s, border-color .15s;
  }
  .cnav .cnav-icon{
    width:28px;height:28px;border-radius:.55rem;display:flex;align-items:center;justify-content:center;
    background:#f1f5f9;color:#64748b;font-size:12px;flex-shrink:0;transition:background .15s,color .15s;
  }
  .cnav:hover{ background:#f8fafc; color:#334155; }
  .cnav.active{ background:rgba(79,70,229,.08); color:var(--lumora-theme); font-weight:700; border-left-color:var(--lumora-theme); }
  .cnav.active .cnav-icon{ background:var(--lumora-theme); color:#fff; }

  /* ── Kartu umum area klien ── */
  .dash-card{
    background:#fff; border:1px solid #eef0f4; border-radius:1rem;
    box-shadow:0 1px 2px rgba(15,23,42,.03);
  }
  .dash-card-hover{ transition:box-shadow .2s ease, transform .2s ease; }
  .dash-card-hover:hover{ box-shadow:0 14px 30px -14px rgba(15,23,42,.16); transform:translateY(-2px); }

  .stat-card{ position:relative; overflow:hidden; }
  .stat-card::before{
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
    background:var(--stat-color, var(--lumora-theme));
  }
  .stat-icon{
    width:42px;height:42px;border-radius:.8rem;display:flex;align-items:center;justify-content:center;
    font-size:15px; box-shadow:0 2px 6px rgba(15,23,42,.06);
  }

  .list-row{ transition:background .15s ease; }
  .list-row:hover{ background:#fafaff; }
</style>
</head>
<body class="lumora-public">

  
  <?php if(session('impersonator_admin_id')): ?>
    <div class="text-white text-center px-3 py-2 d-flex align-items-center justify-content-center gap-3 flex-wrap position-sticky top-0" style="background:#f59e0b;font-size:14px;z-index:1040">
      <span>
        <i class="fa-solid fa-user-shield"></i>
        <b><?php echo e(session('impersonator_admin_name')); ?></b> sedang login sebagai <b><?php echo e($client->name); ?></b>
      </span>
      <form method="POST" action="<?php echo e(route('client.impersonate.stop')); ?>">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:0">
          Kembali ke Admin
        </button>
      </form>
    </div>
  <?php endif; ?>

  
  <header id="clientTopbar" class="d-flex align-items-center px-3 position-sticky" style="top:<?php echo e(session('impersonator_admin_id') ? '41px' : '0'); ?>;z-index:1030">
    <div class="container d-flex align-items-center justify-content-between" style="max-width:72rem">
      <a href="<?php echo e(route('client.dashboard')); ?>" class="d-flex align-items-center gap-2 text-decoration-none">
        <?php
          $brandingDisplay = Setting::get('branding_display', 'logo_and_text');
          $siteLogo = Setting::get('site_logo');
          $siteIcon = Setting::get('site_icon');
        ?>

        <?php if($brandingDisplay === 'logo_and_text'): ?>
          
          <?php if($siteIcon): ?>
            <img src="<?php echo e(route('branding.file', $siteIcon)); ?>" alt="" style="height:32px;width:32px;object-fit:contain">
          <?php elseif($siteLogo): ?>
            <img src="<?php echo e(route('branding.file', $siteLogo)); ?>" alt="" style="height:32px;width:32px;object-fit:contain">
          <?php else: ?>
            <span class="rounded-3 d-flex align-items-center justify-content-center" style="width:32px;height:32px;background:rgba(255,255,255,.15)">
              <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="#fff" stroke-width="2.2"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
            </span>
          <?php endif; ?>
          <span class="fw-bold text-white"><?php echo e($siteName); ?></span>
        <?php elseif($brandingDisplay === 'logo_only' && $siteLogo): ?>
          
          <img src="<?php echo e(route('branding.file', $siteLogo)); ?>" alt="<?php echo e($siteName); ?>" style="height:40px;width:auto;max-width:220px;object-fit:contain">
        <?php else: ?>
          <span class="fw-bold text-white"><?php echo e($siteName); ?></span>
        <?php endif; ?>
      </a>

      <div class="d-flex align-items-center gap-3">
        <a href="<?php echo e(route('catalog.index')); ?>" class="d-none d-sm-flex align-items-center gap-2 text-decoration-none" style="color:rgba(255,255,255,.8);font-size:14px">
          <i class="fa-solid fa-cart-plus"></i> Pesan Layanan Baru
        </a>
        <a href="<?php echo e(route('cart.index')); ?>" class="position-relative d-flex align-items-center justify-content-center text-decoration-none rounded-3" style="width:36px;height:36px;color:rgba(255,255,255,.8)">
          <i class="fa-solid fa-cart-shopping"></i>
          <?php if($cartCount > 0): ?>
            <span class="position-absolute rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="top:-2px;right:-2px;width:16px;height:16px;font-size:10px;background:#f43f5e"><?php echo e($cartCount); ?></span>
          <?php endif; ?>
        </a>
        <span class="d-none d-sm-block" style="color:rgba(255,255,255,.85);font-size:13.5px;font-weight:500"><?php echo e($client->name); ?></span>
        <img src="<?php echo e($client->avatar_url); ?>" class="rounded-circle" style="width:34px;height:34px;border:2px solid rgba(255,255,255,.25)" alt="">
        <form method="POST" action="<?php echo e(route('client.logout')); ?>">
          <?php echo csrf_field(); ?>
          <button type="submit" class="btn btn-link p-0" style="color:rgba(255,255,255,.7)" title="Keluar">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
          </button>
        </form>
      </div>
    </div>
  </header>

  <div class="container d-flex gap-4 py-4" style="max-width:72rem">

    
    <aside class="d-none d-lg-block flex-shrink-0" style="width:232px">
      <div class="position-sticky" style="top:6rem">

        <div class="sidebar-card d-flex align-items-center gap-3">
          <span class="avatar-ring">
            <img src="<?php echo e($client->avatar_url); ?>" alt="">
          </span>
          <div class="min-w-0">
            <p class="fw-bold text-dark mb-0 text-truncate" style="font-size:13.5px"><?php echo e($client->name); ?></p>
            <span class="sidebar-status mt-1"><span class="pulse-dot"></span> Akun Aktif</span>
          </div>
        </div>

        <nav class="d-flex flex-column gap-1">
          <?php $__currentLoopData = $menu; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route($item['route'])); ?>" class="cnav <?php echo e(request()->routeIs($item['match']) ? 'active' : ''); ?>">
              <span class="cnav-icon"><i class="fa-solid <?php echo e($item['icon']); ?>"></i></span>
              <?php echo e($item['label']); ?>

            </a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </nav>
      </div>
    </aside>

    
    <main class="flex-grow-1 min-w-0">
      
      <nav class="d-lg-none d-flex gap-2 mb-4 pb-1" style="overflow-x:auto">
        <?php $__currentLoopData = $menu; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a href="<?php echo e(route($item['route'])); ?>"
             class="px-3 py-2 rounded-pill text-decoration-none text-nowrap"
             style="font-size:12px;font-weight:500;<?php echo e(request()->routeIs($item['match']) ? 'background:var(--lumora-theme);color:#fff' : 'background:#fff;border:1px solid #e2e8f0;color:#475569'); ?>">
            <?php echo e($item['label']); ?>

          </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </nav>

      <?php if (isset($component)) { $__componentOriginal7cfab914afdd05940201ca0b2cbc009b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.toast','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toast'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $attributes = $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $component = $__componentOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>

      <?php echo $__env->yieldContent('content'); ?>
    </main>
  </div>

  
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

  <script src="<?php echo e(asset('assets/js/vendor/bootstrap-5.3.8.bundle.min.js')); ?>"></script>

  <script <?php echo 'nonce="' . e(app(\App\Support\CspNonce::class)->value()) . '"'; ?>>
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

  <?php echo $__env->make('public.partials.livechat', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('partials.csp-actions', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/themes/client-themes/default/client/layout.blade.php ENDPATH**/ ?>