{{-- ══════════ Notifikasi (tema NamaHost) ══════════
     Menggantikan <x-toast />. Membaca flash session dan menyediakan
     window.nhToast(pesan, tipe) untuk dipakai JavaScript halaman lain.
     tipe: success | error | warning | info
     Kalau controller memakai kunci flash lain, tambahkan di array $keys. --}}
@php
  $nhFlash = [];
  foreach (['success' => 'success', 'status' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $key => $type) {
    $val = session($key);
    if (is_string($val) && $val !== '') {
      $nhFlash[] = ['type' => $type, 'message' => $val];
    }
  }
@endphp
<div id="nhToasts" class="nh-toasts in-client" aria-live="polite" aria-atomic="false"></div>
<script>
  (function () {
    var box = document.getElementById('nhToasts');
    var icons = {success: 'bi-check-lg', error: 'bi-x-lg', warning: 'bi-exclamation-lg', info: 'bi-info-lg'};
    var titles = {success: 'Berhasil', error: 'Gagal', warning: 'Perhatian', info: 'Info'};

    function dismiss(el) {
      if (!el || el.dataset.gone) return;
      el.dataset.gone = '1';
      el.classList.add('out');
      setTimeout(function () { el.remove(); }, 220);
    }

    window.nhToast = function (message, type, ms) {
      type = icons[type] ? type : 'info';
      ms = ms || (type === 'error' ? 6500 : 4500);

      var el = document.createElement('div');
      el.className = 'nh-toast ' + type;
      el.setAttribute('role', type === 'error' ? 'alert' : 'status');
      el.style.setProperty('--dur', ms + 'ms');

      var ic = document.createElement('span'); ic.className = 'ic';
      ic.innerHTML = '<i class="bi ' + icons[type] + '"></i>';

      var body = document.createElement('div'); body.className = 'body';
      var t = document.createElement('div'); t.className = 't'; t.textContent = titles[type];
      var m = document.createElement('div'); m.className = 'm'; m.textContent = message;
      body.appendChild(t); body.appendChild(m);

      var x = document.createElement('button');
      x.type = 'button'; x.className = 'x'; x.setAttribute('aria-label', 'Tutup');
      x.innerHTML = '<i class="bi bi-x"></i>';
      x.addEventListener('click', function () { dismiss(el); });

      var bar = document.createElement('span'); bar.className = 'bar';

      el.appendChild(ic); el.appendChild(body); el.appendChild(x); el.appendChild(bar);
      box.appendChild(el);
      setTimeout(function () { dismiss(el); }, ms);
    };

    @json($nhFlash).forEach(function (n) { window.nhToast(n.message, n.type); });
  })();
</script>
