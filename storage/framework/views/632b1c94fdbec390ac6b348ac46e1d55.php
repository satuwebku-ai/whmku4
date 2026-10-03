<?php $tplOptions = \App\Models\MailTemplate::active()->ordered()->get(['id', 'title']); ?>
<?php if($tplOptions->isNotEmpty()): ?>
  <select id="tplPick" class="ix-tpl" title="Sisipkan template balasan">
    <option value="">⚡ Template…</option>
    <?php $__currentLoopData = $tplOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($t->id); ?>"><?php echo e($t->title); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </select>
<?php endif; ?>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/mail/_tpl-select.blade.php ENDPATH**/ ?>