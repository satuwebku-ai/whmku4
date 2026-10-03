<?php
  $domainModuleTabs = [
    ['label' => 'Domain Aktif', 'route' => 'admin.domains'],
    ['label' => 'Cek Domain', 'route' => 'admin.domain.search'],
    ['label' => 'TLD Pricing', 'route' => 'admin.tlds.pricing'],
    ['label' => 'Status TLD', 'route' => 'admin.tlds.index'],
    ['label' => 'ID Protection', 'route' => 'admin.tlds.privacy'],
    ['label' => 'Harga Reseller/Sub-Reseller', 'route' => 'admin.tlds.registrar-pricing'],
    ['label' => 'Domain Premium', 'route' => 'admin.tlds.premium-pricing'],
    ['label' => 'Premium Custom', 'route' => 'admin.tlds.premium-custom'],
    ['label' => 'Registrar', 'route' => 'admin.registrars.index'],
  ];
?>

<div class="d-flex align-items-center gap-1 mb-4 border-bottom flex-wrap">
  <?php $__currentLoopData = $domainModuleTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route($tab['route'])); ?>"
       class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 <?php echo e(request()->routeIs(str_replace('.bootstrap-preview', '', $tab['route'])) || ($tab['label'] === 'Domain Aktif' && request()->routeIs('admin.domains*')) ? 'border-primary text-accent' : 'border-transparent text-muted'); ?>">
      <?php echo e($tab['label']); ?>

    </a>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/domains/_nav.blade.php ENDPATH**/ ?>