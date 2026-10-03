<?php
  use App\Models\Setting;

  $siteName = Setting::get('site_name', config('app.name'));
  $tagline  = Setting::get('site_tagline', 'Hosting cepat, domain murah, aktif dalam hitungan menit.');

  $seoTitle = $siteName . ' — Hosting & Domain Indonesia';
  $seoDescription = $tagline;
?>

<?php $__env->startSection('full-width'); ?>

  <?php echo $__env->make('public.partials.popup-banner', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  
  <?php $__currentLoopData = $homeOrder; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php if($homeSections[$section] ?? false): ?>
      <?php if ($__env->exists('public.home._' . $section)) echo $__env->make('public.home._' . $section, array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('public.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/themes/public-themes/default/public/home.blade.php ENDPATH**/ ?>