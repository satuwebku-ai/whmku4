<?php
  $folder = $folder ?? 'inbox';
  $side = [
      'open'   => \App\Models\MailThread::where('status', 'open')->count(),
      'unread' => \App\Models\MailThread::where('status', 'open')->where('unread_count', '>', 0)->count(),
      'closed' => \App\Models\MailThread::where('status', 'closed')->count(),
  ];
  $mailbox = \App\Models\Setting::get('imap_username') ?: config('mail.from.address');
?>

<link rel="stylesheet" href="<?php echo e(asset('assets/css/lumora-inbox.css')); ?>">

<aside class="ix-side">
  <div>
    <h2>Email</h2>
    <p class="ix-mbox"><?php echo e($mailbox); ?></p>
  </div>

  <a href="<?php echo e(route('admin.mail.compose')); ?>" class="ix-compose"><i class="fa-solid fa-pen" style="font-size:11px"></i> Tulis Email</a>

  <nav class="ix-nav">
    <a href="<?php echo e(route('admin.mail')); ?>" class="<?php echo e($folder === 'inbox' ? 'active' : ''); ?>">
      <i class="fa-solid fa-inbox"></i> Kotak Masuk
      <span class="ix-count <?php echo e($side['unread'] > 0 ? 'hot' : ''); ?>"><?php echo e($side['unread'] > 0 ? $side['unread'] : $side['open']); ?></span>
    </a>
    <a href="<?php echo e(route('admin.mail', ['filter' => 'unread'])); ?>" class="<?php echo e($folder === 'unread' ? 'active' : ''); ?>">
      <i class="fa-regular fa-envelope"></i> Belum Dibaca
      <?php if($side['unread'] > 0): ?><span class="ix-count hot"><?php echo e($side['unread']); ?></span><?php endif; ?>
    </a>
    <a href="<?php echo e(route('admin.mail', ['filter' => 'sent'])); ?>" class="<?php echo e($folder === 'sent' ? 'active' : ''); ?>">
      <i class="fa-regular fa-paper-plane"></i> Terkirim
    </a>
    <a href="<?php echo e(route('admin.mail', ['status' => 'closed'])); ?>" class="<?php echo e($folder === 'closed' ? 'active' : ''); ?>">
      <i class="fa-regular fa-circle-check"></i> Ditutup
      <span class="ix-count"><?php echo e($side['closed']); ?></span>
    </a>
  </nav>

  <div class="mt-auto d-flex flex-column gap-1" style="font-size:11px;color:#94a3b8">
    <a href="<?php echo e(route('admin.templates.index')); ?>" class="text-decoration-none" style="color:#64748b"><i class="fa-solid fa-bolt"></i> Template Balasan</a>
    <a href="<?php echo e(route('admin.mail.settings')); ?>" class="text-decoration-none" style="color:#64748b"><i class="fa-solid fa-robot"></i> Otomatisasi &amp; Template</a>
    <a href="<?php echo e(route('admin.settings.email')); ?>" class="text-decoration-none" style="color:#64748b"><i class="fa-solid fa-sliders"></i> Pengaturan SMTP/IMAP</a>
  </div>
</aside>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/mail/_sidebar.blade.php ENDPATH**/ ?>