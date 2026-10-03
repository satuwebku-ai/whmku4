<?php $__env->startSection('title', 'Komisi Affiliate'); ?>

<?php $__env->startSection('content'); ?>

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 fw-bold text-dark mb-1">Komisi Affiliate</h1>
      <p class="small text-muted mb-0">Setujui komisi supaya masuk ke wallet affiliate.</p>
    </div>
    <a href="<?php echo e(route('admin.affiliate.index')); ?>" class="btn btn-outline-secondary btn-sm">&larr; Program Affiliate</a>
  </div>

  <div class="card border rounded-4 overflow-hidden">
    <form method="GET" class="px-4 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
      <select name="status" class="form-select form-select-sm" style="max-width:12rem" data-auto-submit>
        <option value="">Semua Status</option>
        <?php $__currentLoopData = ['pending' => 'Pending', 'approved' => 'Disetujui', 'cancelled' => 'Dibatalkan']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($val); ?>" <?php if(request('status') === $val): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="small text-uppercase text-muted" style="background:#f8fafc">
            <th class="px-4 py-3">#</th>
            <th class="py-3">Affiliate</th>
            <th class="py-3">Invoice</th>
            <th class="py-3">Nominal</th>
            <th class="py-3">Status</th>
            <th class="text-end px-4 py-3">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $commissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $com): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td class="px-4 py-3"><?php echo e($com->id); ?></td>
              <td class="py-3">
                <a href="<?php echo e(route('admin.affiliate.show', $com->affiliate)); ?>" style="font-family:monospace"><?php echo e($com->affiliate->code); ?></a>
                <br><span class="small text-muted"><?php echo e($com->affiliate->client?->name); ?></span>
              </td>
              <td class="py-3"><?php echo e($com->conversion?->invoice?->invoice_number); ?></td>
              <td class="py-3">Rp <?php echo e(number_format($com->amount, 0, ',', '.')); ?></td>
              <td class="py-3">
                <?php $cBadge = ['pending' => 'badge-soft-warning', 'approved' => 'badge-soft-success', 'cancelled' => 'badge-soft-danger']; ?>
                <span class="badge <?php echo e($cBadge[$com->status] ?? 'badge-soft-danger'); ?>"><?php echo e(ucfirst($com->status)); ?></span>
              </td>
              <td class="text-end px-4 py-3">
                <?php if($com->status === 'pending'): ?>
                  <form method="POST" action="<?php echo e(route('admin.affiliate.commissions.approve', $com)); ?>" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-success btn-sm">Setujui</button>
                  </form>
                  <form method="POST" action="<?php echo e(route('admin.affiliate.commissions.cancel', $com)); ?>" class="d-inline" data-confirm="Batalkan komisi ini?">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="reason" value="Dibatalkan oleh admin">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Batalkan</button>
                  </form>
                <?php elseif($com->status === 'approved'): ?>
                  <form method="POST" action="<?php echo e(route('admin.affiliate.commissions.reverse', $com)); ?>" class="d-inline" data-confirm="Reversal akan mengurangi wallet affiliate. Lanjutkan?">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="reason" value="Refund atau chargeback">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Reversal</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="6" class="text-center text-muted py-5">Belum ada komisi.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if($commissions->hasPages()): ?>
      <div class="px-4 py-3 border-top"><?php echo e($commissions->links('pagination.bootstrap')); ?></div>
    <?php endif; ?>
  </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/affiliate/commissions.blade.php ENDPATH**/ ?>