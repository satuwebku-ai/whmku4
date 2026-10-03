

<?php if(!empty($captcha) && $captcha['required']): ?>
  <div class="mb-3">
    <?php if($captcha['recaptcha']): ?>
      <div class="g-recaptcha" data-sitekey="<?php echo e($captcha['site_key']); ?>"></div>
      <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php else: ?>
      <label class="form-label small fw-medium text-dark"><?php echo e($captcha['question']); ?></label>
      <input type="number" name="captcha_answer" required autocomplete="off"
             class="form-control form-control-sm" placeholder="Jawaban">
      <p class="text-muted mt-1 mb-0" style="font-size:11px">
        Pertanyaan sederhana ini muncul karena ada beberapa percobaan masuk yang gagal.
      </p>
    <?php endif; ?>

    <?php $__errorArgs = ['captcha'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-danger mt-1 mb-0" style="font-size:12px"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
  </div>
<?php endif; ?>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/partials/captcha.blade.php ENDPATH**/ ?>