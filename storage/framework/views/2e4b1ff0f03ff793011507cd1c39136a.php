
<?php
  $features = [
    ['t-teal',   'bi-lightning-charge', 'Server cepat',          'LiteSpeed dan NVMe membuat website terbuka di bawah 1 detik.'],
    ['t-coral',  'bi-shield-check',     'Aman',                  'Sertifikat SSL tersedia, firewall, dan pemindai malware otomatis.'],
    ['t-indigo', 'bi-arrow-repeat',     'Backup otomatis',       'Pulihkan file dan database sendiri dari area klien.'],
    ['t-amber',  'bi-headset',          'Bantuan ramah',         'Tim support siap membantu lewat tiket dan chat.'],
    ['t-teal',   'bi-rocket-takeoff',   'Aktif otomatis',        'Akun hosting dibuat otomatis begitu pembayaran masuk.'],
    ['t-coral',  'bi-window-stack',     'cPanel & installer',    'Pasang WordPress, Laravel, atau Joomla dalam hitungan menit.'],
    ['t-indigo', 'bi-envelope-at',      'Email bisnis',          'Alamat email dengan domain sendiri, siap dipakai di ponsel.'],
    ['t-amber',  'bi-credit-card',      'Bayar mudah',           'Transfer bank, e-wallet, kartu kredit, dan QRIS.'],
  ];
  $steps = [
    ['Pilih domain',        'Cari nama yang cocok dan cek ketersediaannya langsung.'],
    ['Pilih paket hosting', 'Sesuaikan paket dengan kebutuhan website Anda.'],
    ['Bayar & online',      'Akun aktif otomatis setelah pembayaran diterima.'],
  ];
?>

<section id="fitur" class="py-5">
  <div class="container">
    <h2 class="text-center mb-5">Kenapa memilih <?php echo e($siteName ?? 'kami'); ?></h2>
    <div class="row g-4">
      <?php $__currentLoopData = $features; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$tile, $icon, $title, $desc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="col-md-6 col-lg-3">
          <div class="feat h-100">
            <span class="tile <?php echo e($tile); ?>"><i class="bi <?php echo e($icon); ?>"></i></span>
            <h3 class="h6 mt-2"><?php echo e($title); ?></h3>
            <p class="text-body-secondary mb-0"><?php echo e($desc); ?></p>
          </div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</section>

<section class="py-5 bg-body-tertiary">
  <div class="container">
    <h2 class="text-center mb-5">Mulai dalam 3 langkah</h2>
    <div class="row g-4 text-center">
      <?php $__currentLoopData = $steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$title, $desc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="col-md-4">
          <span class="num num-lg mb-3"><?php echo e($loop->iteration); ?></span>
          <h3 class="h6"><?php echo e($title); ?></h3>
          <p class="text-body-secondary"><?php echo e($desc); ?></p>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</section>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/themes/public-themes/namahost/public/home/_benefits.blade.php ENDPATH**/ ?>