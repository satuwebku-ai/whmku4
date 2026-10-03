<?php $__env->startSection('title', 'Email'); ?>

<?php $__env->startSection('content'); ?>

  <?php
    $when = fn ($d) => ! $d ? '' : ($d->isToday() ? $d->format('H:i') : ($d->isSameYear(now()) ? $d->translatedFormat('d M') : $d->format('d/m/y')));
    $titles = ['inbox' => 'Kotak Masuk', 'unread' => 'Belum Dibaca', 'sent' => 'Terkirim', 'closed' => 'Ditutup'];
  ?>

  <div class="ix-app">
    <?php echo $__env->make('admin.mail._sidebar', ['folder' => $folder], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <section class="ix-main">
      <div class="ix-head">
        <h1>
          <i class="fa-regular fa-envelope" style="color:#4f46e5"></i>
          <?php echo e($titles[$folder]); ?>

          <?php if($counts['unread'] > 0 && $folder !== 'closed'): ?><small>(<?php echo e($counts['unread']); ?> pesan baru)</small><?php endif; ?>
        </h1>

        <form method="GET" class="ix-search">
          <?php if($folder === 'closed'): ?> <input type="hidden" name="status" value="closed"><?php endif; ?>
          <?php if(in_array($folder, ['unread', 'sent'], true)): ?> <input type="hidden" name="filter" value="<?php echo e($folder); ?>"><?php endif; ?>
          <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Cari subjek, pengirim, atau isi email...">
          <button type="submit" aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
      </div>

      <form id="mailBulk" method="POST" action="<?php echo e(route('admin.mail.bulk')); ?>"><?php echo csrf_field(); ?></form>

      <div class="ix-toolbar">
        <input type="checkbox" class="ix-chk" id="mailAll" title="Pilih semua">
        <div class="ix-grp">
          <?php if($folder === 'closed'): ?>
            <button type="submit" form="mailBulk" name="action" value="reopen">Buka kembali</button>
          <?php else: ?>
            <button type="submit" form="mailBulk" name="action" value="close">Arsipkan</button>
          <?php endif; ?>
          <button type="submit" form="mailBulk" name="action" value="delete" class="del">Hapus</button>
        </div>

        <div class="ix-pager">
          <?php if($threads->total() > 0): ?>
            <?php echo e($threads->firstItem()); ?>–<?php echo e($threads->lastItem()); ?> dari <?php echo e($threads->total()); ?>

          <?php endif; ?>
          <a class="ix-icon-btn <?php echo e($threads->onFirstPage() ? 'disabled' : ''); ?>" <?php if(! $threads->onFirstPage()): ?> href="<?php echo e($threads->previousPageUrl()); ?>" <?php endif; ?> aria-label="Sebelumnya" style="<?php echo e($threads->onFirstPage() ? 'opacity:.4;pointer-events:none' : ''); ?>"><i class="fa-solid fa-chevron-left"></i></a>
          <a class="ix-icon-btn" <?php if($threads->hasMorePages()): ?> href="<?php echo e($threads->nextPageUrl()); ?>" <?php endif; ?> aria-label="Berikutnya" style="<?php echo e($threads->hasMorePages() ? '' : 'opacity:.4;pointer-events:none'); ?>"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
      </div>

      <div class="ix-list">
        <?php $__empty_1 = true; $__currentLoopData = $threads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $thread): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php
            $unread = $thread->unread_count > 0;
            $last = $thread->latestMessage;
            $snippet = $last ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $last->body), 110) : '';
            $hasFile = $last && ! empty($last->attachments);
            $sentLast = $last && ! $last->isInbound();
          ?>
          <div class="ix-row <?php echo e($unread ? 'unread' : ''); ?>">
            <input type="checkbox" class="ix-chk mail-pick" name="ids[]" value="<?php echo e($thread->id); ?>" form="mailBulk" style="position:relative;z-index:2">
            <a href="<?php echo e(route('admin.mail.show', $thread)); ?>" class="ix-row-link">
              <span class="ix-who">
                <?php if($sentLast): ?><i class="fa-solid fa-reply" style="font-size:10px;color:#94a3b8"></i><?php endif; ?>
                <?php echo e($thread->display_name); ?>

                <small class="text-muted">(<?php echo e($thread->contact_email); ?>)</small>
              </span>
              <span class="ix-subj">
                <b><?php echo e($thread->subject); ?></b>
                <?php if($snippet !== ''): ?> — <?php echo e($snippet); ?><?php endif; ?>
              </span>
              <span class="ix-date">
                <?php if($thread->client_id): ?><span class="ix-pill ok">Klien</span><?php endif; ?>
                <?php if($thread->messages_count > 1): ?><span class="ix-pill mute" title="Jumlah surat"><?php echo e($thread->messages_count); ?></span><?php endif; ?>
                <?php if($hasFile): ?><i class="fa-solid fa-paperclip"></i><?php endif; ?>
                <?php if($unread): ?><span class="ix-badge"><?php echo e($thread->unread_count); ?></span><?php endif; ?>
                <?php echo e($when($thread->last_message_at)); ?>

              </span>
            </a>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="ix-empty">
            <b><?php echo e(request('search') ? 'Tidak ada email yang cocok.' : ($folder === 'closed' ? 'Belum ada email yang ditutup.' : ($folder === 'sent' ? 'Belum ada email terkirim.' : 'Kotak masuk kosong.'))); ?></b>
            Email masuk diambil dari mailbox support tiap beberapa menit. Atur di
            <a href="<?php echo e(route('admin.settings.email')); ?>">Pengaturan → Email</a>.
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <script <?php echo 'nonce="' . e(app(\App\Support\CspNonce::class)->value()) . '"'; ?>>
    (function () {
      const all  = document.getElementById('mailAll');
      const form = document.getElementById('mailBulk');
      const picks = () => Array.from(document.querySelectorAll('.mail-pick'));

      all.addEventListener('change', () => picks().forEach(c => c.checked = all.checked));

      form.addEventListener('submit', function (e) { e.preventDefault(); });

      document.querySelectorAll('button[form="mailBulk"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          if (!picks().some(c => c.checked)) { alert('Pilih minimal satu email dulu.'); return; }
          if (btn.value === 'delete' && !confirm('Hapus email terpilih beserta semua surat dan lampirannya?')) return;
          let h = form.querySelector('input[name="action"]');
          if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = 'action'; form.appendChild(h); }
          h.value = btn.value;
          form.submit();
        });
      });
    })();
  </script>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/mail/index.blade.php ENDPATH**/ ?>