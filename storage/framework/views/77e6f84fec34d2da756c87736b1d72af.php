<?php $__env->startSection('title', $thread->subject); ?>

<?php $__env->startSection('content'); ?>

  <?php
    $folder = $thread->status === 'closed' ? 'closed' : 'inbox';
    $fmtSize = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 2) . ' MB' : number_format(max($b, 1) / 1024, 0) . ' KB';
  ?>

  <div class="ix-app">
    <?php echo $__env->make('admin.mail._sidebar', ['folder' => $folder], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <section class="ix-main">
      <div class="ix-head">
        <h1>
          <a href="<?php echo e(route('admin.mail', $thread->status === 'closed' ? ['status' => 'closed'] : [])); ?>" class="ix-icon-btn" title="Kembali" aria-label="Kembali"><i class="fa-solid fa-arrow-left"></i></a>
          <?php echo e($thread->subject); ?>

          <?php if($thread->status === 'closed'): ?><span class="ix-pill mute">Ditutup</span><?php endif; ?>
        </h1>

        <div class="ix-actions">
          <?php if($thread->client): ?>
            <a href="<?php echo e(route('admin.clients.details', $thread->client)); ?>" class="ix-icon-btn" title="Profil klien"><i class="fa-regular fa-user"></i></a>
          <?php endif; ?>
          <?php if($thread->status === 'open'): ?>
            <form method="POST" action="<?php echo e(route('admin.mail.close', $thread)); ?>"><?php echo csrf_field(); ?>
              <button type="submit" class="ix-icon-btn" title="Tutup / arsipkan"><i class="fa-solid fa-box-archive"></i></button>
            </form>
          <?php else: ?>
            <form method="POST" action="<?php echo e(route('admin.mail.reopen', $thread)); ?>"><?php echo csrf_field(); ?>
              <button type="submit" class="ix-icon-btn" title="Buka kembali"><i class="fa-solid fa-rotate-left"></i></button>
            </form>
          <?php endif; ?>
          <form method="POST" action="<?php echo e(route('admin.mail.delete', $thread)); ?>"
                data-confirm="Hapus email ini beserta semua surat dan lampirannya?"
                data-confirm-title="Hapus Email" data-confirm-style="danger" data-confirm-label="Ya, Hapus">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="submit" class="ix-icon-btn danger" title="Hapus"><i class="fa-regular fa-trash-can"></i></button>
          </form>
        </div>
      </div>

      <div class="ix-msgs">
        <?php $__currentLoopData = $thread->messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $out = ! $message->isInbound();
            $name = $out ? ($message->is_auto ? 'Balasan Otomatis' : ($message->admin?->name ?: 'Staf')) : ($message->from_name ?: $message->from_email);
            $files = $message->attachments ?? [];
            $total = collect($files)->sum('size');
            $ini = strtoupper(mb_substr($name, 0, 1) . (preg_match('/\s(\S)/u', $name, $m) ? $m[1] : ''));
          ?>
          <article class="ix-mail <?php echo e($out ? 'ix-out' : ''); ?>">
            <div class="ix-mail-h">
              <span class="ix-av"><?php echo e($ini); ?></span>
              <div style="min-width:0">
                <span class="nm"><?php echo e($name); ?></span>
                <?php if($message->is_auto): ?><span class="ix-pill"><i class="fa-solid fa-robot"></i> Robot</span><?php endif; ?>
                <span class="to">&nbsp;kepada <?php echo e($out ? $message->to_email : 'saya'); ?></span>
                <div class="to"><?php echo e($message->from_email); ?></div>
              </div>
              <span class="dt" title="<?php echo e($message->created_at->format('d M Y H:i:s')); ?>"><?php echo e($message->created_at->translatedFormat('d M Y, H:i')); ?></span>
            </div>

            <?php if($message->subject): ?><div class="ix-mail-s"><?php echo e($message->subject); ?></div><?php endif; ?>
            <div class="ix-mail-b"><?php echo e($message->body); ?></div>

            <?php if(! empty($files)): ?>
              <div class="ix-att">
                <h4>Lampiran (<?php echo e(count($files)); ?> berkas, <?php echo e($fmtSize($total)); ?>)</h4>
                <?php $__currentLoopData = $files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <a href="<?php echo e(route('admin.mail.attachment', [$message, $i])); ?>">
                    <i class="fa-regular fa-file"></i> <?php echo e($file['name']); ?> <small>(<?php echo e($fmtSize($file['size'] ?? 0)); ?>)</small>
                  </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>

      <form method="POST" action="<?php echo e(route('admin.mail.reply', $thread)); ?>" enctype="multipart/form-data" class="ix-form">
        <?php echo csrf_field(); ?>
        <p style="font-size:13px;font-weight:600;color:#0f172a;margin-bottom:.8rem"><i class="fa-solid fa-reply" style="color:#94a3b8;font-size:11px"></i> Balas <?php echo e($thread->display_name); ?></p>

        <div class="ix-field">
          <label for="mailSubject">Subjek</label>
          <div>
            <input type="text" id="mailSubject" name="subject" value="<?php echo e(old('subject', 'Re: ' . $thread->subject)); ?>" maxlength="200" required>
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
          <textarea id="mailBody" name="body" maxlength="20000" placeholder="Tulis balasan..." required><?php echo e(old('body')); ?></textarea>
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
          <button type="submit" class="ix-btn pri"><i class="fa-solid fa-paper-plane" style="font-size:11px"></i> Kirim Balasan</button>
          <span style="font-size:11px;color:#94a3b8">Balasan pelanggan akan kembali ke thread ini.</span>
        </div>
      </form>
    </section>
  </div>

  <?php echo $__env->make('admin.mail._editor-js', ['ctx' => ['nama' => $thread->display_name, 'email' => $thread->contact_email]], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/mail/show.blade.php ENDPATH**/ ?>