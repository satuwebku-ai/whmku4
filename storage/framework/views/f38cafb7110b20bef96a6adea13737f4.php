<?php
  use App\Models\Setting;
  use App\Services\Cart\CartService;

  $siteName   = Setting::get('site_name', config('app.name', 'Lumora Hosting'));
  $siteLogo   = Setting::get('site_logo');
  $favicon    = Setting::get('site_favicon');
  $themeColor = Setting::get('theme_color', '#6366F1');
  $footerPages = \App\Models\CmsPage::published()->where('show_in_footer', true)->orderBy('sort_order')->get();
  $navMenus = \App\Models\NavMenu::active()->whereNull('parent_id')->with(['page', 'children.page', 'defaultChild.page'])->orderBy('sort_order')->get();
  $cartCount = app(CartService::class)->count();
  $isImpersonating = session('impersonator_admin_id') && auth('client')->check();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <?php echo $__env->make('public.partials.head', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <?php if($favicon): ?>
    <link rel="icon" href="<?php echo e(route('branding.file', $favicon)); ?>">
  <?php endif; ?>

  <link rel="stylesheet" href="<?php echo e(asset('assets/css/vendor/bootstrap-5.3.8.min.css')); ?>?v=<?php echo e(@filemtime(public_path('assets/css/vendor/bootstrap-5.3.8.min.css')) ?: time()); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/css/lumora-public.css')); ?>?v=<?php echo e(@filemtime(public_path('assets/css/lumora-public.css')) ?: time()); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  
  <style>:root{ --lumora-theme: <?php echo e($themeColor); ?>; }</style>
</head>
<body class="lumora-public d-flex flex-column" style="min-height:100vh">

  <?php if($isImpersonating): ?>
    <div id="impersonateBar" class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
      <span>
        <i class="fa-solid fa-user-shield"></i>
        <b><?php echo e(session('impersonator_admin_name')); ?></b> sedang login sebagai <b><?php echo e(auth('client')->user()->name); ?></b>
      </span>
      <form method="POST" action="<?php echo e(route('client.impersonate.stop')); ?>">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:0">
          Kembali ke Admin
        </button>
      </form>
    </div>
  <?php endif; ?>

  <header id="publicHeader" style="<?php echo e($isImpersonating ? 'top:41px' : ''); ?>">
    <div class="container d-flex align-items-center justify-content-between" style="height:64px;max-width:72rem">
      <a href="<?php echo e(route('home')); ?>" class="d-flex align-items-center gap-2 text-decoration-none flex-shrink-0">
        <?php
          $brandingDisplay = \App\Models\Setting::get('branding_display', 'logo_and_text');
          $siteIcon = \App\Models\Setting::get('site_icon');
        ?>

        <?php if($brandingDisplay === 'logo_and_text'): ?>
          
          <?php if($siteIcon): ?>
            <img src="<?php echo e(route('branding.file', $siteIcon)); ?>" alt="" style="height:36px;width:36px;object-fit:contain">
          <?php elseif($siteLogo): ?>
            <img src="<?php echo e(route('branding.file', $siteLogo)); ?>" alt="" style="height:36px;width:36px;object-fit:contain">
          <?php else: ?>
            <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:<?php echo e($themeColor); ?>">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="2.2"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
            </span>
          <?php endif; ?>
          <span class="fw-bold text-dark" style="font-size:1.05rem;letter-spacing:-.01em"><?php echo e($siteName); ?></span>
        <?php elseif($brandingDisplay === 'logo_only' && $siteLogo): ?>
          
          <img src="<?php echo e(route('branding.file', $siteLogo)); ?>" alt="<?php echo e($siteName); ?>" style="height:52px;width:auto;max-width:260px;object-fit:contain">
        <?php else: ?>
          <span class="fw-bold text-dark" style="font-size:1.05rem;letter-spacing:-.01em"><?php echo e($siteName); ?></span>
        <?php endif; ?>
      </a>

      <nav id="publicHeaderNav" class="d-flex align-items-center gap-4">
        <?php $__currentLoopData = $navMenus; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            // Kalau Menu Utama ini di-setting langsung menuju satu
            // Subnav (default_child_id terisi & valid), jangan tampilkan
            // dropdown sama sekali -- $item->resolved_url di bawah sudah
            // otomatis mengarah ke Subnav itu (lihat NavMenu::getResolvedUrlAttribute).
            $validChildren = $item->direct_child_target ? collect() : $item->children->filter(fn ($c) => $c->resolved_url);
          ?>

          <?php if($validChildren->isNotEmpty()): ?>
            <div class="public-menu-item py-2" style="margin:-.5rem 0" data-menu-item>
              <button type="button" data-menu-toggle class="btn btn-link p-0 nav-link d-flex align-items-center gap-1 border-0 <?php echo e($item->active_pattern && request()->routeIs($item->active_pattern) ? 'active' : ''); ?>">
                <?php echo e($item->label); ?>

                <i class="fa-solid fa-chevron-down" style="font-size:9px;opacity:.5"></i>
              </button>
              <div class="public-submenu public-mega" style="--mega-cols: <?php echo e(min(max($validChildren->count(), 1), 3)); ?>">
                <div class="public-submenu-inner">
                  
                  <div class="public-mega-head">
                    <span><?php echo e($item->label); ?></span>
                    <?php if($item->resolved_url): ?>
                      <a href="<?php echo e($item->resolved_url); ?>" class="public-mega-all" <?php if($item->open_in_new_tab): ?> target="_blank" rel="noopener noreferrer" <?php endif; ?>>Lihat semua</a>
                    <?php endif; ?>
                  </div>
                  <div class="public-mega-grid">
                    <?php $__currentLoopData = $validChildren; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <a href="<?php echo e($child->resolved_url); ?>" class="public-mega-card <?php echo e($child->active_pattern && request()->routeIs($child->active_pattern) ? 'active' : ''); ?>" <?php if($child->open_in_new_tab): ?> target="_blank" rel="noopener noreferrer" <?php endif; ?>>
                        <i class="<?php echo e($child->mega_fa_icon); ?>"></i><span><strong><?php echo e($child->label); ?></strong><small><?php echo e($child->mega_description); ?></small></span>
                      </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                  </div>
                </div>
              </div>
            </div>
          <?php elseif($item->resolved_url): ?>
            <a href="<?php echo e($item->resolved_url); ?>"
               <?php if($item->open_in_new_tab): ?> target="_blank" rel="noopener noreferrer" <?php endif; ?>
               class="nav-link text-decoration-none <?php echo e($item->active_pattern && request()->routeIs($item->active_pattern) ? 'active' : ''); ?>">
              <?php echo e($item->label); ?>

            </a>
          <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </nav>

      <div class="d-flex align-items-center gap-3 flex-shrink-0">
        <a href="<?php echo e(route('cart.index')); ?>" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center position-relative" style="width:36px;height:36px;padding:0;border-color:transparent">
          <i class="fa-solid fa-cart-shopping" style="font-size:14px"></i>
          <span id="cartBadge" class="<?php echo e($cartCount > 0 ? '' : 'd-none'); ?>"><?php echo e($cartCount); ?></span>
        </a>
        <?php if(auth()->guard('client')->check()): ?>
          <a href="<?php echo e(route('client.dashboard')); ?>" class="btn btn-outline-secondary btn-sm">Akun Saya</a>
        <?php else: ?>
          <a href="<?php echo e(route('client.login')); ?>" class="btn btn-outline-secondary btn-sm d-none d-sm-inline-flex">Masuk</a>
          <a href="<?php echo e(route('client.register')); ?>" class="btn btn-theme btn-sm">Daftar</a>
        <?php endif; ?>
      </div>
    </div>

    
    <nav id="publicMobileNav" class="align-items-center gap-3 px-3 pb-3 small text-muted" style="overflow-x:auto;display:none">
      <?php $__currentLoopData = $navMenus; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if($item->resolved_url): ?>
          <a href="<?php echo e($item->resolved_url); ?>"
             <?php if($item->open_in_new_tab): ?> target="_blank" rel="noopener noreferrer" <?php endif; ?>
             class="text-nowrap text-decoration-none <?php echo e($item->active_pattern && request()->routeIs($item->active_pattern) ? 'text-theme fw-medium' : 'text-muted'); ?>">
            <?php echo e($item->label); ?>

          </a>
        <?php endif; ?>
        <?php if (! ($item->direct_child_target)): ?>
          <?php $__currentLoopData = $item->children; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(! $child->resolved_url) continue; ?>
            <a href="<?php echo e($child->resolved_url); ?>"
               <?php if($child->open_in_new_tab): ?> target="_blank" rel="noopener noreferrer" <?php endif; ?>
               class="text-nowrap text-decoration-none <?php echo e($child->active_pattern && request()->routeIs($child->active_pattern) ? 'text-theme fw-medium' : 'text-muted'); ?>">
              <i class="fa-solid fa-arrow-turn-up fa-rotate-90" style="font-size:9px"></i> <?php echo e($child->label); ?>

            </a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>
  </header>

  <main class="flex-grow-1">
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

    <?php if (! empty(trim($__env->yieldContent('full-width')))): ?>
      <?php echo $__env->yieldContent('full-width'); ?>
    <?php else: ?>
      <div class="container py-5" style="max-width:72rem">
        <?php echo $__env->yieldContent('content'); ?>
      </div>
    <?php endif; ?>
  </main>

  <footer id="publicFooter">
    <div class="container" style="max-width:72rem">
      <?php if($footerPages->isNotEmpty()): ?>
        <nav class="d-flex flex-wrap gap-3 mb-3">
          <?php $__currentLoopData = $footerPages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('page.show', $fp->slug)); ?>"><?php echo e($fp->title); ?></a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </nav>
      <?php endif; ?>
      <p class="text-muted mb-0" style="font-size:14px"><?php echo e(Setting::get('footer_text') ?: '© ' . date('Y') . ' ' . $siteName . '. Semua hak dilindungi.'); ?></p>
    </div>
  </footer>

  <script src="<?php echo e(asset('assets/js/vendor/bootstrap-5.3.8.bundle.min.js')); ?>"></script>
  <?php echo $__env->make('public.partials.livechat', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  
  <script <?php echo 'nonce="' . e(app(\App\Support\CspNonce::class)->value()) . '"'; ?>>
    (function () {
      const items = document.querySelectorAll('[data-menu-item]');

      items.forEach(function (item) {
        const toggle = item.querySelector('[data-menu-toggle]');
        if (! toggle) return;

        toggle.addEventListener('click', function (e) {
          e.stopPropagation();
          const isOpen = item.classList.contains('open');

          items.forEach(function (other) { other.classList.remove('open'); });

          if (! isOpen) item.classList.add('open');
        });
      });

      document.addEventListener('click', function () {
        items.forEach(function (item) { item.classList.remove('open'); });
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          items.forEach(function (item) { item.classList.remove('open'); });
        }
      });
    })();
  </script>

  
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
<?php echo $__env->make('partials.csp-actions', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/themes/public-themes/default/public/layout.blade.php ENDPATH**/ ?>