<?php $tplData = \App\Models\MailTemplate::active()->ordered()->get(['id', 'subject', 'body'])->keyBy('id'); ?>
<script <?php echo 'nonce="' . e(app(\App\Support\CspNonce::class)->value()) . '"'; ?>>
  (function () {
    const body = document.getElementById('mailBody');
    const files = document.getElementById('mailFiles');
    const list = document.getElementById('mailFileList');
    const lines = document.getElementById('mailLines');
    const words = document.getElementById('mailWords');
    const pick = document.getElementById('tplPick');
    const subj = document.getElementById('mailSubject');
    if (!body) return;

    const tpls = <?php echo json_encode($tplData, 15, 512) ?>;
    const ctx = <?php echo json_encode($ctx ?? [], 15, 512) ?>;
    const site = <?php echo json_encode((string) \App\Models\Setting::get('site_name', config('app.name')), 512) ?>;
    const admin = <?php echo json_encode((string) (auth('admin')->user()->name ?? ''), 15, 512) ?>;

    function count() {
      const v = body.value;
      lines.textContent = 'baris: ' + (v === '' ? 1 : v.split('\n').length);
      words.textContent = 'kata: ' + (v.trim() === '' ? 0 : v.trim().split(/\s+/).length);
    }
    body.addEventListener('input', count);
    count();

    files.addEventListener('change', function () {
      list.innerHTML = '';
      Array.from(files.files).forEach(function (f) {
        const s = document.createElement('span');
        s.textContent = f.name;
        list.appendChild(s);
      });
    });

    function fill(t) {
      const nama = (document.getElementById('toName')?.value || ctx.nama || '').trim() || 'Bapak/Ibu';
      const email = document.getElementById('toEmail')?.value || ctx.email || '';
      return t.split('{nama}').join(nama).split('{email}').join(email)
              .split('{site}').join(site).split('{admin}').join(admin);
    }

    if (pick) {
      pick.addEventListener('change', function () {
        const t = tpls[pick.value];
        pick.value = '';
        if (!t) return;

        const text = fill(t.body);
        const start = body.selectionStart ?? body.value.length;
        const end = body.selectionEnd ?? body.value.length;

        body.value = body.value.trim() === '' ? text : body.value.slice(0, start) + text + body.value.slice(end);
        if (t.subject && subj && (subj.value.trim() === '' || /^re:\s/i.test(subj.value) && subj.value.trim().length < 6)) {
          subj.value = fill(t.subject);
        }
        body.focus();
        count();
      });
    }
  })();
</script>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/admin/mail/_editor-js.blade.php ENDPATH**/ ?>