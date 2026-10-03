<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>

  <div class="mb-4">
    <h1 class="h4 fw-bold text-dark mb-1">Selamat datang, <?php echo e(auth('admin')->user()->name); ?> 👋</h1>
    <p class="small text-muted mb-0">Ringkasan aktivitas hosting &amp; billing hari ini.</p>
  </div>

  
  <div class="row g-3 mb-4">
    <?php
      $iconMap = [
        'users'     => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
        'server'    => 'M4 4h16v6H4zM4 14h16v6H4zM8 8h.01M8 18h.01',
        'clipboard' => 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
        'wallet'    => 'M2 7h20v10H2zM2 10h20M6 15h4',
      ];
      // Warna soft yang benar-benar ada di lumora-admin.css (badge-soft-*),
      // dipakai ulang di sini untuk latar ikon supaya tidak perlu warna baru.
      $colorMap = [
        'users'     => ['bg' => 'rgba(79,70,229,.12)', 'fg' => '#4338ca'],
        'server'    => ['bg' => 'rgba(16,185,129,.14)', 'fg' => '#047857'],
        'clipboard' => ['bg' => 'rgba(245,158,11,.16)', 'fg' => '#b45309'],
        'wallet'    => ['bg' => 'rgba(139,92,246,.14)', 'fg' => '#7c3aed'],
      ];
    ?>

    <?php $__currentLoopData = $stats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php $c = $colorMap[$stat['icon']]; ?>
      <div class="col-6 col-md-6 col-lg-3">
        <div class="card border rounded-4 p-4 h-100">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;background:<?php echo e($c['bg']); ?>;color:<?php echo e($c['fg']); ?>">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="<?php echo e($iconMap[$stat['icon']]); ?>"/></svg>
            </span>
            <span class="badge <?php echo e($stat['trend'] === 'up' ? 'badge-soft-success' : 'badge-soft-danger'); ?>" style="font-size:11px">
              <?php echo e($stat['delta']); ?>

            </span>
          </div>
          <p class="h4 fw-bold text-dark mb-0"><?php echo e($stat['value']); ?></p>
          <p class="small text-muted mb-0 mt-1"><?php echo e($stat['label']); ?></p>
        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  <div class="row g-3">
    
    <div class="col-12 col-lg-8">
      <div class="card border rounded-4 overflow-hidden h-100">
        <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between">
          <h2 class="small fw-bold text-dark mb-0">Order Terbaru</h2>
          <a href="#" class="small fw-medium text-accent text-decoration-none">Lihat semua</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th class="px-4">ID</th>
                <th>Klien</th>
                <th>Produk</th>
                <th>Status</th>
                <th class="text-end px-4">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php
                // Peta status Lumora -> warna badge-soft-* yang tersedia
                // di lumora-admin.css (bukan class baru). Order::status
                // dikirim sebagai string mentah (->value) dari
                // DashboardController -- nilai aslinya dari enum
                // OrderStatus (bukan status Lumora lama 'active'/'pending'
                // dst.), jadi peta ini disesuaikan agar cocok.
                $statusBadge = [
                    'draft' => 'badge-soft-secondary',
                    'requirements_pending' => 'badge-soft-warning',
                    'requirements_review' => 'badge-soft-warning',
                    'requirements_rejected' => 'badge-soft-danger',
                    'requirements_approved' => 'badge-soft-info',
                    'pending' => 'badge-soft-warning', 'unpaid' => 'badge-soft-warning',
                    'pending_payment' => 'badge-soft-warning',
                    'paid' => 'badge-soft-success',
                    'provisioning' => 'badge-soft-info',
                    'completed' => 'badge-soft-success', 'active' => 'badge-soft-success',
                    'failed' => 'badge-soft-danger', 'suspended' => 'badge-soft-danger', 'overdue' => 'badge-soft-danger',
                    'terminated' => 'badge-soft-secondary', 'cancelled' => 'badge-soft-secondary',
                    'expired' => 'badge-soft-secondary',
                ];
              ?>
              <?php $__currentLoopData = $recentOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                  <td class="px-4 fw-medium text-dark"><?php echo e($order['id']); ?></td>
                  <td class="text-muted"><?php echo e($order['client']); ?></td>
                  <td class="text-muted"><?php echo e($order['product']); ?></td>
                  <td>
                    <span class="badge <?php echo e($statusBadge[$order['status']] ?? 'badge-soft-secondary'); ?>">
                      <?php echo e(ucfirst(str_replace('_', ' ', $order['status']))); ?>

                    </span>
                  </td>
                  <td class="text-end px-4 fw-medium text-dark"><?php echo e($order['total']); ?></td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    
    <div class="col-12 col-lg-4">
      <div class="card border rounded-4 p-4 h-100">
        <h2 class="small fw-bold text-dark mb-3">Aksi Cepat</h2>
        <div class="d-flex flex-column gap-2">
          <?php
            $quickActions = [
              ['route' => 'admin.client.add.page', 'icon' => 'fa-user-plus', 'bg' => 'rgba(79,70,229,.12)', 'fg' => '#4338ca', 'label' => 'Tambah Klien Baru'],
              ['route' => 'admin.hosting-account.add.page', 'icon' => 'fa-server', 'bg' => 'rgba(16,185,129,.14)', 'fg' => '#047857', 'label' => 'Buat Hosting Account'],
              ['route' => 'admin.invoice.add.page', 'icon' => 'fa-file-invoice', 'bg' => 'rgba(245,158,11,.16)', 'fg' => '#b45309', 'label' => 'Buat Invoice Manual'],
              ['route' => 'admin.order.add.page', 'icon' => 'fa-cart-plus', 'bg' => 'rgba(139,92,246,.14)', 'fg' => '#7c3aed', 'label' => 'Buat Order'],
              ['route' => 'admin.domain.search', 'icon' => 'fa-globe', 'bg' => 'rgba(14,165,233,.14)', 'fg' => '#0369a1', 'label' => 'Cek Domain'],
            ];
          ?>
          <?php $__currentLoopData = $quickActions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $qa): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route($qa['route'])); ?>" class="d-flex align-items-center gap-3 px-3 py-2 rounded-3 border text-decoration-none small text-dark">
              <span class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;background:<?php echo e($qa['bg']); ?>;color:<?php echo e($qa['fg']); ?>">
                <i class="fa-solid <?php echo e($qa['icon']); ?>" style="font-size:12px"></i>
              </span>
              <?php echo e($qa['label']); ?>

            </a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    </div>
  </div>

  
  <?php if($openTickets->isNotEmpty()): ?>
    <div class="card border rounded-4 overflow-hidden mt-3">
      <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between">
        <h2 class="small fw-bold text-dark mb-0">Tiket Butuh Perhatian</h2>
        <a href="<?php echo e(route('admin.tickets')); ?>" class="small fw-medium text-accent text-decoration-none">Lihat semua</a>
      </div>
      <div>
        <?php $__currentLoopData = $openTickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a href="<?php echo e(route('admin.tickets.details', $ticket)); ?>" class="d-flex align-items-center justify-content-between px-4 py-3 text-decoration-none small border-bottom">
            <div>
              <p class="fw-medium text-dark mb-0"><?php echo e($ticket->subject); ?></p>
              <p class="text-muted mb-0" style="font-size:12px"><?php echo e($ticket->ticket_number); ?> · <?php echo e($ticket->client->name ?? '—'); ?> · <?php echo e($ticket->last_reply_at?->diffForHumans()); ?></p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
              <span class="badge badge-soft-warning"><?php echo e(ucfirst($ticket->priority)); ?></span>
              <span class="badge badge-soft-primary"><?php echo e($ticket->status_label); ?></span>
            </div>
          </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  <?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/dashboard/index.blade.php ENDPATH**/ ?>