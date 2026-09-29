{{-- ══════════ Toast notifikasi admin ══════════
     Sebelumnya toast memakai class gaya Bootstrap 5.3 (bg-success-subtle,
     text-danger-emphasis, dst) yang TIDAK ADA di framework.css buatan
     sendiri, jadi kotaknya transparan tanpa warna. Sekarang gayanya
     ditulis eksplisit di sini dan seluruh nilainya (posisi, durasi,
     ukuran, warna) bisa diatur dari Pengaturan → Tampilan Notifikasi.

     Dipakai luas (flash session success/error/warning/info dari ratusan
     controller), jadi WAJIB ada di layout admin. Dari JavaScript lain
     bisa dipanggil:  lumoraToast('success', 'Tersimpan.') --}}
@php
  $__ts = \App\Support\ToastStyle::current();

  $__toasts = [];
  foreach (array_keys(\App\Support\ToastStyle::TYPES) as $__type) {
      if (session($__type) && is_string(session($__type))) {
          $__toasts[] = ['type' => $__type, 'message' => session($__type)];
      }
  }
@endphp

<div id="toastWrap" class="lumora-toast-wrap lt-pos-{{ $__ts['toast_position'] }}" aria-live="polite" aria-atomic="false"></div>

<style>
  :root { {!! \App\Support\ToastStyle::cssVars($__ts) !!} }

  .lumora-toast-wrap {
    position: fixed; z-index: 1080;
    display: flex; flex-direction: column; gap: .6rem;
    width: min(var(--lt-width, 360px), calc(100vw - 1.5rem));
    padding: 1rem; pointer-events: none;
  }
  .lumora-toast-wrap.lt-pos-top-right     { top: 0; right: 0; }
  .lumora-toast-wrap.lt-pos-top-left      { top: 0; left: 0; }
  .lumora-toast-wrap.lt-pos-top-center    { top: 0; left: 50%; transform: translateX(-50%); }
  .lumora-toast-wrap.lt-pos-bottom-right  { bottom: 0; right: 0; flex-direction: column-reverse; }
  .lumora-toast-wrap.lt-pos-bottom-left   { bottom: 0; left: 0; flex-direction: column-reverse; }
  .lumora-toast-wrap.lt-pos-bottom-center { bottom: 0; left: 50%; transform: translateX(-50%); flex-direction: column-reverse; }

  .lumora-toast {
    position: relative; overflow: hidden; pointer-events: auto;
    display: flex; align-items: flex-start; gap: .75rem;
    padding: .85rem 1rem;
    border: 1px solid var(--lt-info-border); border-radius: var(--lt-radius, 14px);
    background: var(--lt-info-bg); color: var(--lt-info-text);
    box-shadow: var(--lt-shadow, none);
    font-size: 13px; line-height: 1.5;
    animation: lumoraToastIn .25s ease-out both;
  }
  .lumora-toast.is-success { background: var(--lt-success-bg); border-color: var(--lt-success-border); color: var(--lt-success-text); }
  .lumora-toast.is-error   { background: var(--lt-error-bg);   border-color: var(--lt-error-border);   color: var(--lt-error-text); }
  .lumora-toast.is-warning { background: var(--lt-warning-bg); border-color: var(--lt-warning-border); color: var(--lt-warning-text); }
  .lumora-toast.is-info    { background: var(--lt-info-bg);    border-color: var(--lt-info-border);    color: var(--lt-info-text); }

  .lumora-toast .lt-icon  { flex-shrink: 0; margin-top: 2px; font-size: 15px; }
  .lumora-toast .lt-msg   { flex: 1 1 auto; min-width: 0; word-break: break-word; }
  .lumora-toast .lt-close {
    flex-shrink: 0; border: 0; background: transparent; color: inherit;
    opacity: .55; cursor: pointer; padding: 2px 4px; font-size: 12px; line-height: 1;
  }
  .lumora-toast .lt-close:hover { opacity: 1; }
  .lumora-toast .lt-bar {
    position: absolute; left: 0; bottom: 0; height: 3px; width: 100%;
    background: currentColor; opacity: .35; transform-origin: left;
    animation-name: lumoraToastBar; animation-timing-function: linear; animation-fill-mode: forwards;
  }
  .lumora-toast:hover .lt-bar { animation-play-state: paused; }
  .lumora-toast.lt-out { opacity: 0; transform: translateY(-6px); transition: opacity .2s ease-in, transform .2s ease-in; }

  @keyframes lumoraToastIn  { from { opacity: 0; transform: translateY(-8px) scale(.98); } to { opacity: 1; transform: none; } }
  @keyframes lumoraToastBar { from { transform: scaleX(1); } to { transform: scaleX(0); } }

  @media (prefers-reduced-motion: reduce) {
    .lumora-toast { animation: none; }
    .lumora-toast .lt-bar { display: none; }
  }
</style>

<script>
  window.lumoraToastConfig = {!! json_encode(\App\Support\ToastStyle::jsConfig($__ts)) !!};

  (function () {
    const ICONS = {
      success: 'fa-circle-check',
      error: 'fa-circle-exclamation',
      warning: 'fa-triangle-exclamation',
      info: 'fa-circle-info',
    };

    window.lumoraDismissToast = function (el) {
      if (!el || el.classList.contains('lt-out')) return;
      el.classList.add('lt-out');
      setTimeout(() => el.remove(), 220);
    };

    window.lumoraToast = function (type, message, target) {
      type = ICONS[type] ? type : 'info';
      const cfg = window.lumoraToastConfig;
      const wrap = target || document.getElementById('toastWrap');
      if (!wrap || !message) return null;

      const el = document.createElement('div');
      el.className = 'lumora-toast is-' + type;
      el.setAttribute('role', type === 'error' || type === 'warning' ? 'alert' : 'status');

      if (cfg.showIcon) {
        const icon = document.createElement('i');
        icon.className = 'fa-solid ' + ICONS[type] + ' lt-icon';
        el.appendChild(icon);
      }

      const msg = document.createElement('span');
      msg.className = 'lt-msg';
      msg.textContent = message;
      el.appendChild(msg);

      const close = document.createElement('button');
      close.type = 'button';
      close.className = 'lt-close';
      close.setAttribute('aria-label', 'Tutup');
      close.innerHTML = '<i class="fa-solid fa-xmark"></i>';
      close.addEventListener('click', () => lumoraDismissToast(el));
      el.appendChild(close);

      const ms = (type === 'error' || type === 'warning') ? cfg.durationError : cfg.duration;

      if (cfg.showProgress) {
        const bar = document.createElement('span');
        bar.className = 'lt-bar';
        bar.style.animationDuration = ms + 'ms';
        el.appendChild(bar);
      }

      wrap.appendChild(el);

      // Auto-tutup; berhenti sementara selama kursor di atas toast.
      let timer = null;
      let remaining = ms;
      let startedAt = 0;
      const start = () => { startedAt = Date.now(); timer = setTimeout(() => lumoraDismissToast(el), remaining); };
      el.addEventListener('mouseenter', () => { clearTimeout(timer); remaining -= Date.now() - startedAt; });
      el.addEventListener('mouseleave', () => { if (remaining > 0) start(); });
      start();

      return el;
    };

    const flashed = {{ \Illuminate\Support\Js::from($__toasts) }};
    flashed.forEach((t) => window.lumoraToast(t.type, t.message));
  })();
</script>
