// Service worker khusus Push Notification. Sengaja minimal -- tidak
// melakukan caching/offline apa pun, cuma menerima & menampilkan push,
// dan mengarahkan klik notifikasi ke halaman yang relevan.

self.addEventListener('push', function (event) {
  let data = { title: 'Notifikasi Baru', body: '', url: '/' };

  try {
    if (event.data) {
      data = Object.assign(data, event.data.json());
    }
  } catch (e) {
    // Payload bukan JSON valid -- tampilkan versi paling minimal
    // daripada gagal total dan tidak menampilkan apa-apa.
    data.body = event.data ? event.data.text() : '';
  }

  const options = {
    body: data.body || '',
    icon: data.icon || '/favicon.png',
    badge: '/favicon.png',
    data: { url: data.url || '/' },
    tag: data.tag || undefined, // notifikasi baru dgn tag sama akan menimpa yang lama, bukan menumpuk
  };

  event.waitUntil(self.registration.showNotification(data.title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();

  const targetUrl = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
      // Kalau tab dengan URL yang sama sudah terbuka, fokus ke situ saja
      // daripada buka tab baru lagi.
      for (const client of windowClients) {
        if (client.url === targetUrl && 'focus' in client) {
          return client.focus();
        }
      }

      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
