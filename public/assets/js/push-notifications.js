/**
 * Helper Push Notification, dipakai baik di panel admin maupun area
 * klien. Config (urlVapidKey, urlSubscribe, urlUnsubscribe) beda per
 * area karena route-nya terpisah (guard admin vs klien) -- makanya
 * dikirim lewat data-attribute pada tombolnya, bukan di-hardcode di sini.
 */
window.LumoraPush = (function () {
  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function isSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window;
  }

  async function currentSubscription() {
    const registration = await navigator.serviceWorker.ready;
    return registration.pushManager.getSubscription();
  }

  /**
   * Status dipakai untuk render tombol yang benar saat halaman dimuat:
   * 'unsupported' | 'denied' | 'subscribed' | 'unsubscribed'
   */
  async function status() {
    if (!isSupported()) return 'unsupported';
    if (Notification.permission === 'denied') return 'denied';

    try {
      await navigator.serviceWorker.register('/sw.js');
      const sub = await currentSubscription();
      return sub ? 'subscribed' : 'unsubscribed';
    } catch (e) {
      return 'unsubscribed';
    }
  }

  async function subscribe(urlVapidKey, urlSubscribe) {
    const registration = await navigator.serviceWorker.register('/sw.js');

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      throw new Error('Izin notifikasi ditolak.');
    }

    const keyRes = await fetch(urlVapidKey);
    const { key } = await keyRes.json();

    if (!key) {
      throw new Error('Push Notification belum diaktifkan admin (kunci VAPID belum dibuat).');
    }

    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(key),
    });

    await fetch(urlSubscribe, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({ subscription: subscription.toJSON() }),
    });

    return subscription;
  }

  async function unsubscribe(urlUnsubscribe) {
    const sub = await currentSubscription();
    if (!sub) return;

    const endpoint = sub.endpoint;
    await sub.unsubscribe();

    await fetch(urlUnsubscribe, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({ endpoint }),
    });
  }

  /**
   * Pasang ke satu tombol toggle -- otomatis atur teks & perilakunya
   * sesuai status saat ini. Dipanggil dari layout dengan elemen tombol
   * + 3 URL (vapid-key, subscribe, unsubscribe) miliknya sendiri.
   */
  function attachToggle(button, urls) {
    if (!button) return;

    function render(state) {
      button.disabled = state === 'unsupported' || state === 'denied';
      button.dataset.pushState = state;

      const labels = {
        unsupported: 'Push tidak didukung browser ini',
        denied: 'Notifikasi diblokir — aktifkan lewat pengaturan browser',
        subscribed: 'Matikan Notifikasi Push',
        unsubscribed: 'Aktifkan Notifikasi Push',
      };

      button.textContent = labels[state] || labels.unsubscribed;
    }

    status().then(render);

    button.addEventListener('click', async function () {
      const state = button.dataset.pushState;
      button.disabled = true;

      try {
        if (state === 'subscribed') {
          await unsubscribe(urls.unsubscribe);
          render('unsubscribed');
        } else {
          await subscribe(urls.vapidKey, urls.subscribe);
          render('subscribed');
        }
      } catch (e) {
        alert(e.message || 'Terjadi kesalahan.');
        button.disabled = false;
      }
    });
  }

  return { isSupported, status, subscribe, unsubscribe, attachToggle };
})();
