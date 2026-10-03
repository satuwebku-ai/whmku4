<?php $__env->startSection('title', 'Checkout'); ?>

<?php $__env->startSection('content'); ?>

  <a href="<?php echo e(route('cart.index')); ?>" class="text-decoration-none text-muted" style="font-size:12px">&larr; Kembali ke Keranjang</a>

  <h1 class="h4 fw-bold text-dark mt-2 mb-4">Konfirmasi Pesanan</h1>

  <?php if($issues): ?>
    <div class="card-public p-4 mb-4" style="border-color:#fecaca!important;background:#fef2f2">
      <p class="fw-semibold mb-2" style="font-size:14px;color:#b91c1c"><i class="fa-solid fa-circle-exclamation"></i> Ada item yang belum bisa diproses:</p>
      <ul class="mb-0 ps-4" style="font-size:14px;color:#dc2626">
        <?php $__currentLoopData = $issues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $issue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <li style="margin-bottom:.25rem"><?php echo e($issue); ?></li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </ul>
      <a href="<?php echo e(route('cart.index')); ?>" class="btn btn-outline-danger btn-sm mt-3">Perbaiki di Keranjang</a>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-12 col-lg-8 d-flex flex-column gap-4">

      
      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-3">Ringkasan Pesanan</h2>
        <div>
          <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="py-3 border-bottom d-flex align-items-start justify-content-between gap-3">
              <div>
                <?php if($item['type'] === 'product'): ?>
                  <p class="fw-medium text-dark mb-0" style="font-size:14px"><?php echo e($item['name']); ?></p>
                  <p class="text-muted mb-0" style="font-size:11px"><?php echo e(optional(\App\Models\Product::find($item['product_id'] ?? null))->cycleLabel($item['billing_cycle']) ?? (\App\Models\Product::CYCLES[$item['billing_cycle']] ?? $item['billing_cycle'])); ?></p>
                  <?php if(!empty($item['domain_name'])): ?>
                    <p class="text-muted mt-1 mb-0" style="font-size:11px">
                      <i class="fa-solid fa-globe" style="font-size:10px"></i>
                      <?php echo e($item['domain_mode'] === 'register' ? 'Daftar domain baru' : 'Pakai domain sendiri'); ?>: <?php echo e($item['domain_name']); ?>

                    </p>
                  <?php endif; ?>
                  <?php if(!empty($item['selected_options'])): ?>
                    <ul class="list-unstyled mt-1 mb-0 d-flex flex-column gap-1">
                      <?php $__currentLoopData = $item['selected_options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="text-muted d-flex align-items-center gap-1" style="font-size:11px">
                          <i class="fa-solid fa-plus" style="font-size:9px"></i> <?php echo e($opt['name']); ?>

                          <span class="text-dark">— Rp <?php echo e(number_format($opt['price'], 0, ',', '.')); ?></span>
                        </li>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                  <?php endif; ?>
                <?php elseif($item['type'] === 'addon'): ?>
                  <p class="fw-medium text-dark mb-0" style="font-size:14px"><?php echo e($item['name']); ?></p>
                  <p class="text-muted mb-0" style="font-size:11px">Lisensi digital — <?php echo e(\App\Models\Addon::CYCLE_LABELS[$item['billing_cycle']] ?? $item['billing_cycle']); ?><?php if(! empty($item['license_ip'])): ?> &middot; IP <?php echo e($item['license_ip']); ?><?php endif; ?></p>
                <?php elseif($item['type'] === 'domain_premium'): ?>
                  <p class="fw-medium text-dark mb-0" style="font-size:14px"><?php echo e($item['domain_name'] ?? '-'); ?></p>
                  <p class="text-muted mb-0" style="font-size:11px">Domain premium — <?php echo e($item['years'] ?? 1); ?> tahun</p>
                <?php else: ?>
                  <p class="fw-medium text-dark mb-0" style="font-size:14px"><?php echo e($item['domain_name'] ?? '-'); ?></p>
                  <p class="text-muted mb-0" style="font-size:11px">Registrasi domain — <?php echo e($item['years'] ?? 1); ?> tahun</p>
                <?php endif; ?>
              </div>
              <p class="fw-semibold text-dark mb-0 flex-shrink-0" style="font-size:14px">Rp <?php echo e(number_format($item['price'], 0, ',', '.')); ?></p>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <div class="pt-3 mt-2 border-top d-flex flex-column gap-2">
          <div class="d-flex justify-content-between text-muted" style="font-size:14px">
            <span>Subtotal</span>
            <span>Rp <?php echo e(number_format($subtotal, 0, ',', '.')); ?></span>
          </div>
          <?php if($coupon): ?>
            <div class="d-flex justify-content-between text-success" style="font-size:14px">
              <span>Kupon <?php echo e($coupon->code); ?> (<?php echo e($coupon->value_label); ?>)</span>
              <span>- Rp <?php echo e(number_format($discount, 0, ',', '.')); ?></span>
            </div>
          <?php endif; ?>
          <div class="d-flex justify-content-between fw-bold text-dark pt-1" style="font-size:1rem">
            <span>Total</span>
            <span>Rp <?php echo e(number_format($subtotal - $discount, 0, ',', '.')); ?></span>
          </div>
        </div>
      </div>

      
      <div class="card-public p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="small fw-bold text-dark mb-0">Data Penagihan</h2>
          <a href="<?php echo e(route('client.profile')); ?>" class="text-decoration-none text-theme" style="font-size:12px">Edit Profil</a>
        </div>
        <div class="row g-3">
          <div class="col-sm-6"><p class="text-muted mb-0" style="font-size:11px">Nama</p><p class="text-dark mb-0" style="font-size:14px"><?php echo e($client->name); ?></p></div>
          <div class="col-sm-6"><p class="text-muted mb-0" style="font-size:11px">Email</p><p class="text-dark mb-0" style="font-size:14px"><?php echo e($client->email); ?></p></div>
          <div class="col-sm-6"><p class="text-muted mb-0" style="font-size:11px">Telepon</p><p class="text-dark mb-0" style="font-size:14px"><?php echo e($client->phone ?: '—'); ?></p></div>
          <div class="col-sm-6"><p class="text-muted mb-0" style="font-size:11px">Alamat</p><p class="text-dark mb-0" style="font-size:14px"><?php echo e($client->address ?: '—'); ?>, <?php echo e($client->city); ?></p></div>
        </div>

        <?php if($items->contains(fn ($i) => ($i['type'] ?? null) === 'domain' || ($i['domain_mode'] ?? null) === 'register')): ?>
          <?php if(blank($client->state) || blank($client->postal_code)): ?>
            <div class="mt-3 rounded-3 px-3 py-2" style="background:#fffbeb;border:1px solid #fde68a;font-size:12px;color:#92400e">
              <i class="fa-solid fa-triangle-exclamation"></i>
              Ada pendaftaran domain di pesanan ini. Provinsi & kode pos Anda belum lengkap — data ini wajib untuk registrasi
              domain (WHOIS). Lengkapi dulu di <a href="<?php echo e(route('client.profile')); ?>" class="text-decoration-underline fw-medium" style="color:inherit">halaman Profil</a>
              supaya domain bisa diproses otomatis setelah pembayaran.
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-12 col-lg-4 d-flex flex-column gap-4">
      
      <div class="card-public p-4">
        <h2 class="small fw-bold text-dark mb-3">Kode Kupon</h2>

        <?php if($coupon): ?>
          <div class="d-flex align-items-center justify-content-between rounded-3 px-3 py-2" style="background:#f0fdf4;border:1px solid #a7f3d0">
            <div>
              <p class="fw-semibold mb-0" style="font-size:14px;color:#047857"><?php echo e($coupon->code); ?></p>
              <p class="mb-0" style="font-size:11px;color:#059669">Potongan <?php echo e($coupon->value_label); ?> diterapkan</p>
              <?php if($coupon->applies_to === 'specific'): ?>
                <p class="mt-1 mb-0" style="font-size:10px;color:#10b981">
                  Hanya berlaku untuk produk tertentu di keranjang — item lain (mis. registrasi domain) tidak ikut didiskon.
                </p>
              <?php endif; ?>
            </div>
            <form method="POST" action="<?php echo e(route('client.checkout.coupon.remove')); ?>">
              <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
              <button type="submit" class="btn btn-link p-0 text-success" style="font-size:12px;text-decoration:underline">Batalkan</button>
            </form>
          </div>
        <?php else: ?>
          <form method="POST" action="<?php echo e(route('client.checkout.coupon')); ?>" class="d-flex gap-2">
            <?php echo csrf_field(); ?>
            <input type="text" name="code" placeholder="Masukkan kode kupon" class="form-control text-uppercase">
            <button type="submit" class="btn btn-outline-secondary flex-shrink-0">Pakai</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="card-public p-4" style="position:sticky;top:6rem">
        <h2 class="small fw-bold text-dark mb-1">Selesaikan Pesanan</h2>
        <p class="text-muted mb-3" style="font-size:12px">
          Setelah dikonfirmasi, invoice akan dibuat dan Anda diarahkan ke halaman pembayaran.
          Layanan aktif otomatis begitu invoice lunas.
        </p>

        <form method="POST" action="<?php echo e(route('client.checkout.store')); ?>">
          <?php echo csrf_field(); ?>
          <button type="submit" class="btn btn-theme w-100" <?php echo e($issues ? 'disabled' : ''); ?>>
            <i class="fa-solid fa-check" style="font-size:11px"></i> Buat Pesanan
          </button>
        </form>
      </div>
    </div>
  </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('client.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/themes/client-themes/default/client/checkout/index.blade.php ENDPATH**/ ?>