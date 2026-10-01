<script @nonce>
  (function () {
    const body = document.getElementById('mailBody');
    const files = document.getElementById('mailFiles');
    const list = document.getElementById('mailFileList');
    const lines = document.getElementById('mailLines');
    const words = document.getElementById('mailWords');
    if (!body) return;

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
  })();
</script>
