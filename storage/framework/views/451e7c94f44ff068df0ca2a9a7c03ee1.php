<?php $__env->startSection('title', 'Domain Premium'); ?>

<?php $__env->startSection('content'); ?>

  <?php echo $__env->make('admin.domains._nav', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Domain Premium</h1>
      <p class="small text-muted mb-0" style="max-width:48rem">
        Harga MODAL keluarga .id ditarik otomatis dari DNAMA (tingkat harganya ditetapkan PANDI
        berdasarkan jumlah karakter) — harga JUAL diisi manual di sini dan tersimpan permanen,
        tidak pernah ditimpa oleh sinkronisasi ulang. Tingkat yang Jual Register-nya masih
        kosong <strong>tidak dijual</strong> ke publik, dan harga jual tidak boleh di bawah modal.
      </p>
    </div>

    <form method="GET" class="d-flex gap-2">
      <select name="registrar" class="form-select form-select-sm" style="width:14rem" data-auto-submit>
        <option value="">— Pilih Registrar —</option>
        <?php $__currentLoopData = $registrars; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($r->id); ?>" <?php if($selected && $selected->id === $r->id): echo 'selected'; endif; ?>><?php echo e($r->name); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </form>
  </div>

  <?php if(! $selected): ?>
    <div class="card border rounded-4 p-5 text-center text-muted">
      Pilih registrar dulu di atas untuk mengelola harga domain premium-nya.
    </div>
  <?php else: ?>

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="small fw-bold text-dark mb-0">Keluarga .id — <?php echo e($selected->name); ?></h2>
      <form method="POST" action="<?php echo e(route('admin.tld.premium-pricing.sync')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="registrar_id" value="<?php echo e($selected->id); ?>">
        <button type="submit" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-cloud-arrow-down" style="font-size:11px"></i> Sinkron Harga Modal dari <?php echo e($selected->name); ?>

        </button>
      </form>
    </div>

    <?php if($familyRows->isEmpty()): ?>
      <div class="card border rounded-4 p-5 text-center text-muted mb-4">
        Belum ada data. Tekan "Sinkron Harga Modal" di atas untuk menariknya dari <?php echo e($selected->name); ?>.
      </div>
    <?php else: ?>
      <form method="POST" action="<?php echo e(route('admin.tld.premium-pricing.update')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="registrar_id" value="<?php echo e($selected->id); ?>">

        <div class="card border rounded-4 overflow-hidden mb-4">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr class="small text-uppercase text-muted" style="background:#f8fafc">
                  <th class="px-4 py-3">Ekstensi</th>
                  <th class="text-end py-3">Modal Register</th>
                  <th class="text-end py-3">Modal Renew</th>
                  <th class="text-end py-3">Modal Transfer</th>
                  <th class="text-end py-3" style="width:9rem">Jual Register</th>
                  <th class="text-end py-3" style="width:9rem">Jual Renew</th>
                  <th class="text-end py-3" style="width:9rem">Jual Transfer</th>
                  <th class="py-3">Disinkron</th>
                </tr>
              </thead>
              <tbody>
                <?php $__currentLoopData = $familyRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <tr>
                    <td class="px-4 py-2 fw-medium text-dark">
                      <?php echo e($row->label); ?>

                      <?php if($row->is_premium): ?>
                        <span class="badge ms-1" style="font-size:9px;background:#fef3c7;color:#92400e">Premium</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end py-2 text-muted"><?php echo e($row->cost_register !== null ? number_format($row->cost_register, 0, ',', '.') . ' ' . $row->cost_currency : '—'); ?></td>
                    <td class="text-end py-2 text-muted"><?php echo e($row->cost_renew !== null ? number_format($row->cost_renew, 0, ',', '.') . ' ' . $row->cost_currency : '—'); ?></td>
                    <td class="text-end py-2 text-muted"><?php echo e($row->cost_transfer !== null ? number_format($row->cost_transfer, 0, ',', '.') . ' ' . $row->cost_currency : '—'); ?></td>
                    <td class="py-2">
                      <input type="number" step="1" min="0" name="rows[<?php echo e($row->id); ?>][sell_register_price]" value="<?php echo e($row->sell_register_price); ?>" class="form-control form-control-sm text-end" placeholder="belum diisi">
                    </td>
                    <td class="py-2">
                      <input type="number" step="1" min="0" name="rows[<?php echo e($row->id); ?>][sell_renew_price]" value="<?php echo e($row->sell_renew_price); ?>" class="form-control form-control-sm text-end" placeholder="belum diisi">
                    </td>
                    <td class="py-2">
                      <input type="number" step="1" min="0" name="rows[<?php echo e($row->id); ?>][sell_transfer_price]" value="<?php echo e($row->sell_transfer_price); ?>" class="form-control form-control-sm text-end" placeholder="belum diisi">
                    </td>
                    <td class="py-2 text-muted" style="font-size:11px">
                      <?php echo e($row->cost_synced_at?->diffForHumans() ?? '—'); ?>

                    </td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </tbody>
            </table>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-floppy-disk" style="font-size:11px"></i> Simpan Harga Jual
        </button>
      </form>
    <?php endif; ?>

    <?php if($genericRows->isNotEmpty()): ?>
      <div class="mt-5">
        <h2 class="small fw-bold text-dark mb-1">Ekstensi Generik — cek &amp; pesan lewat tiket</h2>
        <p class="text-muted mb-3" style="font-size:12px">
          Harga domain premium generik ditentukan PER-NAMA oleh <?php echo e($selected->name); ?> (tidak ada
          daftar tetap seperti keluarga .id), dan pemesanannya diproses manual, bukan lewat API
          registrasi biasa. Daftar di bawah cuma referensi ekstensi apa saja yang bisa dicek.
        </p>
        <div class="d-flex flex-wrap gap-2">
          <?php $__currentLoopData = $genericRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <span class="badge badge-soft-secondary" style="font-size:12px"><?php echo e($row->extension); ?></span>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/tlds/premium-pricing.blade.php ENDPATH**/ ?>