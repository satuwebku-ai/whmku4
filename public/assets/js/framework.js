/*!
 * framework.js — pengganti bootstrap.bundle.min.js buatan sendiri.
 * Meng-cover interaksi yang dipakai di halaman ini: dropdown, modal,
 * tab, tooltip, popover, dan tombol dismiss alert (data-bs-dismiss).
 * Dibuat 100% offline, tidak butuh koneksi internet sama sekali.
 * Global `bootstrap.Collapse` disediakan sebagai stub kosong supaya
 * kompatibel kalau ada kode lama yang memanggilnya, tapi sidebar di
 * halaman ini sudah pakai mekanisme sendiri (lihat script utama).
 */
(function () {
  'use strict';

  /* ════════════ DROPDOWN ════════════ */
  function closeAllDropdowns(except) {
    document.querySelectorAll('.dropdown-menu.show').forEach(function (menu) {
      if (menu !== except) menu.classList.remove('show');
    });
  }
  document.addEventListener('click', function (e) {
    const toggle = e.target.closest('[data-bs-toggle="dropdown"]');
    if (toggle) {
      e.preventDefault();
      const parent = toggle.closest('.dropdown');
      const menu = parent ? parent.querySelector('.dropdown-menu') : null;
      if (!menu) return;
      const isOpen = menu.classList.contains('show');
      closeAllDropdowns(isOpen ? null : menu);
      menu.classList.toggle('show', !isOpen);
      return;
    }
    // Klik di luar dropdown yang terbuka -> tutup semua
    if (!e.target.closest('.dropdown-menu')) closeAllDropdowns(null);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAllDropdowns(null);
  });

  /* ════════════ TABS ════════════ */
  document.addEventListener('click', function (e) {
    const tabBtn = e.target.closest('[data-bs-toggle="tab"]');
    if (!tabBtn) return;
    e.preventDefault();
    const targetSel = tabBtn.getAttribute('data-bs-target');
    const target = document.querySelector(targetSel);
    if (!target) return;
    const nav = tabBtn.closest('.nav');
    if (nav) nav.querySelectorAll('.nav-link').forEach(function (l) { l.classList.remove('active'); });
    tabBtn.classList.add('active');
    const content = target.closest('.tab-content') || document;
    content.querySelectorAll('.tab-pane').forEach(function (p) { p.classList.remove('active', 'show'); });
    target.classList.add('active', 'show');
  });

  /* ════════════ MODAL ════════════ */
  let modalBackdrop = null;
  function ensureBackdrop() {
    if (!modalBackdrop) {
      modalBackdrop = document.createElement('div');
      modalBackdrop.className = 'modal-backdrop';
      document.body.appendChild(modalBackdrop);
    }
    return modalBackdrop;
  }
  function openModal(modalEl) {
    if (!modalEl) return;
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    document.body.classList.add('modal-open');
    const bd = ensureBackdrop();
    requestAnimationFrame(function () { bd.classList.add('show'); });
  }
  function closeModal(modalEl) {
    if (!modalEl) return;
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    document.body.classList.remove('modal-open');
    if (modalBackdrop) { modalBackdrop.classList.remove('show'); modalBackdrop.remove(); modalBackdrop = null; }
  }
  document.addEventListener('click', function (e) {
    const opener = e.target.closest('[data-bs-toggle="modal"]');
    if (opener) {
      e.preventDefault();
      const sel = opener.getAttribute('data-bs-target');
      openModal(document.querySelector(sel));
      return;
    }
    const closer = e.target.closest('[data-bs-dismiss="modal"]');
    if (closer) {
      e.preventDefault();
      closeModal(closer.closest('.modal'));
      return;
    }
    // Klik backdrop modal (area gelap di luar dialog) -> tutup
    if (e.target.classList.contains('modal') && e.target.classList.contains('show')) {
      closeModal(e.target);
    }
  });

  /* ════════════ ALERT DISMISS ════════════ */
  document.addEventListener('click', function (e) {
    const closer = e.target.closest('[data-bs-dismiss="alert"]');
    if (!closer) return;
    const alertEl = closer.closest('.alert');
    if (!alertEl) return;
    alertEl.style.transition = 'opacity .15s linear';
    alertEl.style.opacity = '0';
    setTimeout(function () { alertEl.remove(); }, 150);
  });

  /* ════════════ TOOLTIP ════════════ */
  function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      if (el.dataset.tooltipInit) return;
      el.dataset.tooltipInit = '1';
      let bubble = null;
      el.addEventListener('mouseenter', function () {
        const text = el.getAttribute('title') || el.getAttribute('data-bs-title');
        if (!text) return;
        el.setAttribute('data-original-title', text);
        el.removeAttribute('title');
        bubble = document.createElement('div');
        bubble.className = 'fw-tooltip';
        bubble.textContent = text;
        document.body.appendChild(bubble);
        const rect = el.getBoundingClientRect();
        bubble.style.left = (rect.left + rect.width / 2) + 'px';
        bubble.style.top = (rect.top - 8) + 'px';
        requestAnimationFrame(function () { bubble.classList.add('show'); });
      });
      el.addEventListener('mouseleave', function () {
        if (bubble) { bubble.remove(); bubble = null; }
        const original = el.getAttribute('data-original-title');
        if (original) el.setAttribute('title', original);
      });
    });
  }

  /* ════════════ POPOVER ════════════ */
  function initPopovers() {
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
      if (el.dataset.popoverInit) return;
      el.dataset.popoverInit = '1';
      let pop = null;
      function show() {
        const title = el.getAttribute('title') || el.getAttribute('data-bs-title') || '';
        const content = el.getAttribute('data-bs-content') || '';
        pop = document.createElement('div');
        pop.className = 'fw-popover';
        pop.innerHTML = (title ? '<div class="fw-popover-title">' + title + '</div>' : '') +
                         '<div class="fw-popover-body">' + content + '</div>';
        document.body.appendChild(pop);
        const rect = el.getBoundingClientRect();
        pop.style.left = (rect.left + rect.width / 2) + 'px';
        pop.style.top = (rect.top - 10) + 'px';
        requestAnimationFrame(function () { pop.classList.add('show'); });
      }
      function hide() { if (pop) { pop.remove(); pop = null; } }
      el.addEventListener('mouseenter', show);
      el.addEventListener('mouseleave', hide);
      el.addEventListener('focus', show);
      el.addEventListener('blur', hide);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initTooltips();
    initPopovers();
  });
  if (document.readyState !== 'loading') { initTooltips(); initPopovers(); }

  /* ════════════ Stub kompatibilitas (dipanggil optional oleh script lain) ════════════ */
  window.bootstrap = window.bootstrap || {
    Collapse: {
      getOrCreateInstance: function () {
        return { hide: function () {}, show: function () {}, toggle: function () {} };
      }
    },
    Tooltip: function (el) { return el; },
    Popover: function (el) { return el; }
  };
})();
