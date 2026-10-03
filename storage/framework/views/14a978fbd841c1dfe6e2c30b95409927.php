<?php
  $settingTabs = [
    ['label' => 'Umum', 'route' => 'admin.settings.general'],
    ['label' => 'Halaman Depan', 'route' => 'admin.settings.homepage'],
    ['label' => 'Persyaratan', 'route' => 'admin.settings.requirements.index'],
    ['label' => 'PDF Invoice', 'route' => 'admin.settings.pdf-invoice'],
    ['label' => 'SEO', 'route' => 'admin.settings.seo'],
    ['label' => 'Analytics', 'route' => 'admin.settings.analytics'],
    ['label' => 'Affiliate', 'route' => 'admin.settings.affiliate'],
    ['label' => 'Notifikasi', 'route' => 'admin.settings.notifications'],
    ['label' => 'Tampilan Notifikasi', 'route' => 'admin.settings.toast.edit'],
    ['label' => 'Keamanan', 'route' => 'admin.settings.security'],
    ['label' => 'Email', 'route' => 'admin.settings.email'],
    ['label' => 'Live Chat', 'route' => 'admin.settings.livechat'],
    ['label' => 'Trafik AI', 'route' => 'admin.ai-usage.index'],
    ['label' => 'cPanel Aplikasi', 'route' => 'admin.self-cpanel.edit'],
    ['label' => 'Cron Jobs', 'route' => 'admin.cron.index'],
  ];
?>

<div class="d-flex align-items-center gap-1 mb-4 border-bottom flex-wrap">
  <?php $__currentLoopData = $settingTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route($tab['route'])); ?>"
       class="px-3 py-2 small fw-medium text-decoration-none border-bottom border-2 <?php echo e(request()->routeIs(str_replace('.bootstrap-preview', '', $tab['route'])) ? 'border-primary text-accent' : 'border-transparent text-muted'); ?>">
      <?php echo e($tab['label']); ?>

    </a>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/settings/_nav.blade.php ENDPATH**/ ?>