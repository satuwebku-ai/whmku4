
<script <?php echo 'nonce="' . e(app(\App\Support\CspNonce::class)->value()) . '"'; ?>>
(function () {
  var actions = (window.LumoraActions = window.LumoraActions || {});
  var byId = function (id) { return id ? document.getElementById(id) : null; };
  var unsafeUrl = /^\s*(javascript|data|vbscript):/i;

  document.addEventListener('change', function (e) {
    var auto = e.target.closest && e.target.closest('[data-auto-submit]');
    if (auto && auto.form) { auto.form.submit(); return; }
    var nav = e.target.closest && e.target.closest('[data-navigate-on-change]');
    if (nav && nav.value && !unsafeUrl.test(nav.value)) { window.location = nav.value; }
  });

  document.addEventListener('click', function (e) {
    var el = e.target.closest && e.target.closest('[data-action]');
    if (!el) { return; }
    var target = byId(el.getAttribute('data-target'));

    switch (el.getAttribute('data-action')) {
      case 'history-back': history.back(); break;
      case 'window-close': window.close(); break;
      case 'select': if (typeof el.select === 'function') { el.select(); } break;
      case 'toggle': if (target) { target.classList.toggle('d-none'); } break;
      case 'show':
        if (target) { target.classList.remove('d-none'); }
        if (el.hasAttribute('data-hide-self')) { el.classList.add('d-none'); }
        break;
      case 'hide': if (target) { target.classList.add('d-none'); } break;
      case 'submit-form': if (target && typeof target.submit === 'function') { target.submit(); } break;
      case 'set-value': if (target) { target.value = el.getAttribute('data-value') || ''; } break;
      case 'copy':
        if (target && navigator.clipboard) {
          navigator.clipboard.writeText(target.value).catch(function () {});
          var icon = el.getAttribute('data-copied-icon');
          if (icon) {
            var i = document.createElement('i');
            i.className = 'fa-solid ' + icon;
            i.style.fontSize = '11px';
            el.replaceChildren(i);
          }
        }
        break;
      case 'call':
        var name = el.getAttribute('data-call');
        var fn = Object.prototype.hasOwnProperty.call(actions, name) ? actions[name] : null;
        if (typeof fn === 'function') {
          var args = [];
          try { args = JSON.parse(el.getAttribute('data-args') || '[]'); } catch (err) { args = []; }
          if (!Array.isArray(args)) { args = []; }
          if (el.hasAttribute('data-pass-el')) { args.push(el); }
          fn.apply(el, args);
        }
        break;
    }
  });
})();
</script>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/partials/csp-actions.blade.php ENDPATH**/ ?>