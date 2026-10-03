
<?php if($banners->isNotEmpty()): ?>
  <div class="position-relative rounded-4 overflow-hidden mb-4" id="promoBannerCarousel">
    <?php $__currentLoopData = $banners; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $banner): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="promo-slide <?php echo e($i === 0 ? '' : 'd-none'); ?>" data-slide="<?php echo e($i); ?>">
        <?php if($banner->link_url): ?>
          <a href="<?php echo e($banner->link_url); ?>" <?php if($banner->open_in_new_tab): ?> target="_blank" rel="noopener noreferrer" <?php endif; ?> class="d-block position-relative">
        <?php else: ?>
          <div class="position-relative">
        <?php endif; ?>

          <img src="<?php echo e(route('banner.file', $banner->image)); ?>" alt="<?php echo e($banner->title); ?>" class="w-100" style="display:block;height:auto">

          <?php
            // Judul "-" dipakai sebagai penanda "tanpa judul" (field ini
            // wajib diisi di form admin), jadi diperlakukan sama seperti
            // kosong supaya bayangan gelap tidak muncul kalau gambar
            // banner sudah punya teksnya sendiri.
            $bannerTitle = trim((string) $banner->title) === '-' ? '' : $banner->title;
          ?>

          <?php if($bannerTitle || $banner->subtitle || $banner->button_text): ?>
            <div class="position-absolute top-0 start-0 end-0 bottom-0 d-flex align-items-center" style="background:linear-gradient(to right, rgba(0,0,0,.6), rgba(0,0,0,.2) 60%, transparent)">
              <div class="px-4" style="max-width:32rem">
                <?php if($bannerTitle): ?>
                  <h2 class="text-white fw-bold mb-1" style="font-size:1.4rem"><?php echo e($bannerTitle); ?></h2>
                <?php endif; ?>
                <?php if($banner->subtitle): ?>
                  <p class="text-white mb-3" style="opacity:.8;font-size:14px"><?php echo e($banner->subtitle); ?></p>
                <?php endif; ?>
                <?php if($banner->button_text): ?>
                  <span class="btn btn-theme d-inline-flex"><?php echo e($banner->button_text); ?></span>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

        <?php if($banner->link_url): ?>
          </a>
        <?php else: ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <?php if($banners->count() > 1): ?>
      <div class="position-absolute d-flex gap-2" style="bottom:12px;left:50%;transform:translateX(-50%)">
        <?php $__currentLoopData = $banners; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $banner): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <button type="button" class="promo-dot rounded-circle border-0" data-dot="<?php echo e($i); ?>" style="width:8px;height:8px;background:<?php echo e($i === 0 ? '#fff' : 'rgba(255,255,255,.4)'); ?>"></button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if($banners->count() > 1): ?>
    <script <?php echo 'nonce="' . e(app(\App\Support\CspNonce::class)->value()) . '"'; ?>>
      (function () {
        const slides = document.querySelectorAll('#promoBannerCarousel .promo-slide');
        const dots = document.querySelectorAll('#promoBannerCarousel .promo-dot');
        let current = 0;

        function show(index) {
          slides.forEach((s, i) => s.classList.toggle('d-none', i !== index));
          dots.forEach((d, i) => { d.style.background = i === index ? '#fff' : 'rgba(255,255,255,.4)'; });
          current = index;
        }

        dots.forEach(dot => dot.addEventListener('click', () => show(parseInt(dot.dataset.dot))));

        setInterval(() => show((current + 1) % slides.length), 5000);
      })();
    </script>
  <?php endif; ?>
<?php endif; ?>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/themes/public-themes/namahost/public/_promo-banner-carousel.blade.php ENDPATH**/ ?>