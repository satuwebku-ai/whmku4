<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  /* DomPDF hanya mendukung sebagian CSS — dijaga sederhana dan pakai
     inline-safe properties supaya render-nya konsisten. */
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; margin: 0; }
  .topbar { background: #4f46e5; height: 6px; width: 100%; margin-bottom: 26px; }
  .header { display: table; width: 100%; margin-bottom: 30px; }
  .header .col { display: table-cell; vertical-align: top; }
  .header .right { text-align: right; }
  .brand { font-size: 20px; font-weight: bold; color: #4f46e5; }
  .muted { color: #64748b; }
  .title { font-size: 24px; font-weight: bold; margin: 0 0 4px; color: #0f172a; }
  .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: bold; }
  .badge-paid    { background: #d1fae5; color: #047857; }
  .badge-unpaid  { background: #fef3c7; color: #b45309; }
  .badge-overdue { background: #fee2e2; color: #b91c1c; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 20px; }
  table.items th { text-align: left; font-size: 11px; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; padding: 8px 6px; }
  table.items td { padding: 10px 6px; border-bottom: 1px solid #f1f5f9; }
  table.items .amount { text-align: right; }
  .totals { width: 260px; margin-left: auto; margin-top: 10px; }
  .totals table { width: 100%; }
  .totals td { padding: 4px 0; }
  .totals .grand td { font-size: 15px; font-weight: bold; color: #4f46e5; border-top: 2px solid #1e293b; padding-top: 8px; }
  .footer { margin-top: 40px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #94a3b8; }
</style>
</head>
<body>

  <div class="topbar"></div>

  <div class="header">
    <div class="col">
      <?php $pdfLogo = \App\Models\Setting::get('site_logo'); ?>
      <?php if($pdfLogo): ?>
        
        <?php $logoPath = \Illuminate\Support\Facades\Storage::disk('local')->path('branding/' . $pdfLogo); ?>
        <?php if(file_exists($logoPath)): ?>
          <img src="<?php echo e($logoPath); ?>" style="max-height:50px;max-width:220px;margin-bottom:6px;" alt="<?php echo e(config('app.name')); ?>">
        <?php else: ?>
          <div class="brand"><?php echo e(config('app.name')); ?></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="brand"><?php echo e(config('app.name')); ?></div>
      <?php endif; ?>
      <p class="muted" style="margin:4px 0 0">
        <?php echo e(\App\Models\Setting::get('company_address', '')); ?>

      </p>
    </div>
    <div class="col right">
      <p class="title">INVOICE</p>
      <p class="muted" style="margin:0"><?php echo e($invoice->invoice_number); ?></p>
      <p style="margin-top:8px">
        <span class="badge badge-<?php echo e($invoice->is_overdue ? 'overdue' : $invoice->status); ?>">
          <?php echo e($invoice->is_overdue ? 'OVERDUE' : strtoupper($invoice->status)); ?>

        </span>
      </p>
    </div>
  </div>

  <div class="header">
    <div class="col">
      <p class="muted" style="margin:0 0 4px">Ditagihkan kepada</p>
      <p style="margin:0;font-weight:bold"><?php echo e($invoice->client->name); ?></p>
      <p class="muted" style="margin:2px 0"><?php echo e($invoice->client->email); ?></p>
      <?php if($invoice->client->company): ?>
        <p class="muted" style="margin:2px 0"><?php echo e($invoice->client->company); ?></p>
      <?php endif; ?>
    </div>
    <div class="col right">
      <p class="muted" style="margin:0 0 4px">Tanggal Terbit</p>
      <p style="margin:0 0 10px"><?php echo e($invoice->issue_date->format('d M Y')); ?></p>
      <p class="muted" style="margin:0 0 4px">Jatuh Tempo</p>
      <p style="margin:0"><?php echo e($invoice->due_date->format('d M Y')); ?></p>
    </div>
  </div>

  <table class="items">
    <thead>
      <tr>
        <th>Deskripsi</th>
        <th class="amount">Jumlah</th>
      </tr>
    </thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $invoice->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td><?php echo e($item->description); ?></td>
          <td class="amount">Rp <?php echo e(number_format($item->amount, 0, ',', '.')); ?></td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr>
          <td>Invoice <?php echo e($invoice->invoice_number); ?></td>
          <td class="amount">Rp <?php echo e(number_format($invoice->amount, 0, ',', '.')); ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="totals">
    <table>
      <tr><td class="muted">Subtotal</td><td class="amount">Rp <?php echo e(number_format($invoice->amount, 0, ',', '.')); ?></td></tr>
      <tr><td class="muted">Pajak</td><td class="amount">Rp <?php echo e(number_format($invoice->tax, 0, ',', '.')); ?></td></tr>
      <?php if($invoice->discount > 0): ?>
        <tr><td class="muted">Diskon</td><td class="amount">- Rp <?php echo e(number_format($invoice->discount, 0, ',', '.')); ?></td></tr>
      <?php endif; ?>
      <tr class="grand"><td>Total</td><td class="amount">Rp <?php echo e(number_format($invoice->total, 0, ',', '.')); ?></td></tr>
    </table>
  </div>

  <?php if($invoice->status === 'paid'): ?>
    <p style="margin-top:30px">
      <span class="badge badge-paid">LUNAS</span>
      pada <?php echo e($invoice->paid_at?->format('d M Y')); ?>

      <?php if($invoice->payment_method): ?> via <?php echo e($invoice->payment_method); ?> <?php endif; ?>
    </p>
  <?php endif; ?>

  <?php
    $pdfBanner = \App\Models\PromoBanner::live()->forPage('pdf_invoice')->orderBy('sort_order')->first();
  ?>
  <?php if($pdfBanner): ?>
    <?php
      // DomPDF butuh path file FISIK, sama seperti logo di atas.
      $pdfBannerPath = \Illuminate\Support\Facades\Storage::disk('local')->path('banners/' . $pdfBanner->image);
    ?>
    <?php if(file_exists($pdfBannerPath)): ?>
      <div style="margin-top:30px">
        <img src="<?php echo e($pdfBannerPath); ?>" style="width:100%;max-width:100%;border-radius:6px;">
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="footer">
    <p><?php echo e(\App\Models\Setting::get('footer_text') ?: config('app.name') . ' — ' . now()->year); ?></p>
    <p>Dokumen ini dibuat otomatis oleh sistem dan sah tanpa tanda tangan basah.</p>
  </div>

</body>
</html>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/themes/client-themes/default/client/invoices/pdf.blade.php ENDPATH**/ ?>