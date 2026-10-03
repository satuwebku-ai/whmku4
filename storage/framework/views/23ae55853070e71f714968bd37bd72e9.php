<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e($title ?? 'Terjadi Kesalahan'); ?> — <?php echo e(config('app.name')); ?></title>
  <style>html{visibility:hidden}</style>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<script <?php echo 'nonce="' . e(app(\App\Support\CspNonce::class)->value()) . '"'; ?>>window.addEventListener("load",function(){document.documentElement.style.visibility="visible"});</script>
  <script <?php echo 'nonce="' . e(app(\App\Support\CspNonce::class)->value()) . '"'; ?>>setTimeout(function(){document.documentElement.style.visibility='visible'},2500)</script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="antialiased bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center px-6">

  

  <div class="max-w-md w-full text-center">
    <div class="w-16 h-16 rounded-2xl bg-<?php echo e($color ?? 'slate'); ?>-100 text-<?php echo e($color ?? 'slate'); ?>-600 flex items-center justify-center mx-auto mb-6">
      <i class="fa-solid <?php echo e($icon ?? 'fa-circle-exclamation'); ?> text-2xl"></i>
    </div>

    <p class="text-6xl font-bold text-slate-200 mb-2"><?php echo e($code ?? ''); ?></p>
    <h1 class="text-xl font-bold text-slate-800 mb-2"><?php echo e($title ?? 'Terjadi Kesalahan'); ?></h1>
    <p class="text-sm text-slate-500 mb-8 leading-relaxed"><?php echo e($message ?? 'Silakan coba lagi beberapa saat lagi.'); ?></p>

    <div class="flex items-center justify-center gap-3">
      <a href="<?php echo e(url('/')); ?>" class="btn btn-primary inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition-colors">
        <i class="fa-solid fa-house text-xs"></i> Kembali ke Beranda
      </a>
      <button data-action="history-back" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-100 transition-colors">
        <i class="fa-solid fa-arrow-left text-xs"></i> Halaman Sebelumnya
      </button>
    </div>
  </div>

<?php echo $__env->make('partials.csp-actions', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/errors/minimal.blade.php ENDPATH**/ ?>