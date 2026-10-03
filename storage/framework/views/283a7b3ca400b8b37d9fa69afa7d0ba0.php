<?php $__env->startSection('title', 'Tulis Email'); ?>

<?php $__env->startSection('content'); ?>

  <div class="ix-app">
    <?php echo $__env->make('admin.mail._sidebar', ['folder' => 'compose'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <section class="ix-main">
      <div class="ix-head">
        <h1><i class="fa-regular fa-pen-to-square" style="color:#4f46e5"></i> Pesan baru</h1>
      </div>

      <form method="POST" action="<?php echo e(route('admin.mail.store')); ?>" enctype="multipart/form-data" class="ix-form">
        <?php echo csrf_field(); ?>

        <div class="ix-field">
          <label for="toEmail">Kepada</label>
          <div>
            <input type="email" id="toEmail" name="to_email" value="<?php echo e(old('to_email', $to)); ?>" maxlength="255" placeholder="pelanggan@contoh.com" required>
            <?php $__errorArgs = ['to_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="ix-err"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
        </div>

        <div class="ix-field">
          <label for="toName">Nama</label>
          <div>
            <input type="text" id="toName" name="to_name" value="<?php echo e(old('to_name')); ?>" maxlength="120" placeholder="Opsional">
            <?php $__errorArgs = ['to_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="ix-err"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
        </div>

        <div class="ix-field">
          <label for="mailSubject">Subjek</label>
          <div>
            <input type="text" id="mailSubject" name="subject" value="<?php echo e(old('subject')); ?>" maxlength="200" required>
            <?php $__errorArgs = ['subject'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="ix-err"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
        </div>

        <div class="ix-editor">
          <div class="ix-editor-bar">
            <label title="Lampirkan berkas"><i class="fa-solid fa-paperclip"></i> Lampirkan
              <input type="file" id="mailFiles" name="attachments[]" multiple class="d-none">
            </label>
            <?php echo $__env->make('admin.mail._tpl-select', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <span style="margin-left:auto">maks. 5 berkas @ 5 MB · JPG, PNG, WEBP, PDF, TXT, ZIP</span>
          </div>
          <textarea id="mailBody" name="body" maxlength="20000" required><?php echo e(old('body')); ?></textarea>
          <div class="ix-files" id="mailFileList"></div>
          <div class="ix-editor-foot"><span id="mailLines">baris: 1</span><span id="mailWords">kata: 0</span></div>
        </div>
        <?php $__errorArgs = ['body'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="ix-err mb-2"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <?php $__errorArgs = ['attachments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="ix-err mb-2"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <?php $__errorArgs = ['attachments.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="ix-err mb-2"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <div class="ix-actions">
          <button type="submit" class="ix-btn pri">Kirim</button>
          <a href="<?php echo e(route('admin.mail')); ?>" class="ix-btn sec">Batal</a>
        </div>
        <p style="font-size:11px;color:#94a3b8;margin:.8rem 0 0">Dikirim dari alamat pengirim di Pengaturan → Email. Balasan pelanggan masuk ke Kotak Masuk sebagai thread yang sama.</p>
      </form>
    </section>
  </div>

  <?php echo $__env->make('admin.mail._editor-js', ['ctx' => []], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/mail/compose.blade.php ENDPATH**/ ?>