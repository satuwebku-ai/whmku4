<?php $__env->startSection('title', 'Billing Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h1 class="h4 fw-bold text-dark mb-1">Billing Dashboard</h1>
    <p class="small text-muted mb-0">Ringkasan pendapatan, piutang, pembayaran, dan anomali billing.</p>
  </div>
  <a href="<?php echo e(route('admin.invoices')); ?>" class="btn btn-outline-secondary btn-sm">Kelola Invoice</a>
</div>

<form method="GET" class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex align-items-end gap-2 flex-wrap">
    <div>
      <label class="form-label small fw-semibold">Dari</label>
      <input type="date" name="from" value="<?php echo e($from->toDateString()); ?>" class="form-control form-control-sm">
    </div>
    <div>
      <label class="form-label small fw-semibold">Sampai</label>
      <input type="date" name="to" value="<?php echo e($to->toDateString()); ?>" class="form-control form-control-sm">
    </div>
    <button class="btn btn-primary btn-sm">Terapkan</button>
    <a href="<?php echo e(route('admin.billing.dashboard')); ?>" class="btn btn-light btn-sm">Bulan Ini</a>
  </div>
</form>

<div class="row g-3 mb-4">
  <?php
    $cards = [
      ['label'=>'Pendapatan Periode', 'value'=>'Rp '.number_format((float)$revenue,0,',','.'), 'icon'=>'fa-chart-line'],
      ['label'=>'Piutang Terbuka', 'value'=>'Rp '.number_format((float)$outstanding,0,',','.'), 'icon'=>'fa-file-invoice-dollar'],
      ['label'=>'Overdue', 'value'=>'Rp '.number_format((float)$overdue,0,',','.'), 'icon'=>'fa-triangle-exclamation'],
      ['label'=>'Pembayaran Hari Ini', 'value'=>'Rp '.number_format((float)$paymentsToday,0,',','.'), 'icon'=>'fa-money-bill-transfer'],
    ];
  ?>
  <?php $__currentLoopData = $cards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div><div class="small text-muted mb-2"><?php echo e($card['label']); ?></div><div class="h5 fw-bold mb-0"><?php echo e($card['value']); ?></div></div>
            <div class="rounded-3 bg-light p-2"><i class="fa-solid <?php echo e($card['icon']); ?> text-secondary"></i></div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-xl-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-3"><h2 class="h6 fw-bold mb-0">Pendapatan Harian</h2><span class="small text-muted"><?php echo e($from->format('d M Y')); ?> — <?php echo e($to->format('d M Y')); ?></span></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Tanggal</th><th class="text-end">Pendapatan</th></tr></thead><tbody>
          <?php $__empty_1 = true; $__currentLoopData = $dailyRevenue; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr><td><?php echo e($day['label']); ?></td><td class="text-end fw-semibold">Rp <?php echo e(number_format($day['value'],0,',','.')); ?></td></tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="2" class="text-center text-muted py-4">Belum ada data.</td></tr>
          <?php endif; ?>
        </tbody></table></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-xl-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3">Kontrol Billing</h2>
        <div class="d-flex justify-content-between py-2 border-bottom"><span class="small text-muted">Invoice lunas</span><strong><?php echo e(number_format($paidCount)); ?></strong></div>
        <div class="d-flex justify-content-between py-2 border-bottom"><span class="small text-muted">Top-up periode</span><strong>Rp <?php echo e(number_format((float)$topupRevenue,0,',','.')); ?></strong></div>
        <div class="d-flex justify-content-between py-2"><span class="small text-muted">Anomali charge</span><strong class="<?php echo e($reconciliationIssues['paid_invoice_without_charge'] ? 'text-danger' : 'text-success'); ?>"><?php echo e($reconciliationIssues['paid_invoice_without_charge']); ?></strong></div>
        <div class="d-flex justify-content-between py-2"><span class="small text-muted">Payment mismatch</span><strong class="<?php echo e($reconciliationIssues['paid_payment_without_invoice'] ? 'text-danger' : 'text-success'); ?>"><?php echo e($reconciliationIssues['paid_payment_without_invoice']); ?></strong></div>
        <a href="<?php echo e(route('admin.invoices.overdue')); ?>" class="btn btn-outline-warning btn-sm w-100 mt-2">Lihat Invoice Overdue</a>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-xl-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-3"><h2 class="h6 fw-bold mb-0">Pembayaran Terbaru</h2><a href="<?php echo e(route('admin.payments')); ?>" class="small">Lihat semua</a></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Reference</th><th>Klien</th><th>Invoice</th><th class="text-end">Total</th></tr></thead><tbody>
          <?php $__empty_1 = true; $__currentLoopData = $recentPayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr><td><?php echo e($payment->reference); ?></td><td><?php echo e($payment->client?->name ?? '—'); ?></td><td><?php echo e($payment->invoice?->invoice_number ?? '—'); ?></td><td class="text-end fw-semibold">Rp <?php echo e(number_format((float)$payment->total,0,',','.')); ?></td></tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada pembayaran.</td></tr>
          <?php endif; ?>
        </tbody></table></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-xl-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3">Invoice Overdue Terlama</h2>
        <?php $__empty_1 = true; $__currentLoopData = $overdueInvoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <a href="<?php echo e(route('admin.invoices.details', $invoice)); ?>" class="d-flex justify-content-between align-items-center py-2 border-bottom text-decoration-none">
            <div><div class="small fw-semibold text-dark"><?php echo e($invoice->invoice_number); ?></div><div class="small text-muted"><?php echo e($invoice->client?->name ?? '—'); ?> · jatuh tempo <?php echo e(optional($invoice->due_date)->format('d M Y')); ?></div></div>
            <span class="small fw-bold text-danger">Rp <?php echo e(number_format((float)$invoice->total,0,',','.')); ?></span>
          </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="text-muted small py-3">Tidak ada invoice overdue.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/billing/dashboard.blade.php ENDPATH**/ ?>