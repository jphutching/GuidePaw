const CACHE_NAME = 'psd-logbook-v16';
const APP_SHELL = [
  '/psd_logbook/',
  '/psd_logbook/index.php',
  '/psd_logbook/login.php',
  '/psd_logbook/register.php',
  '/psd_logbook/log_entry.php',
  '/psd_logbook/view_logs.php',
  '/psd_logbook/settings.php',
  '/psd_logbook/profile.php',
  '/psd_logbook/manifest.json',
  '/psd_logbook/app.js',
  '/psd_logbook/csrf_token.php',
  '/psd_logbook/appointment_notifications.php',
  '/psd_logbook/appointments.php',
  '/psd_logbook/dog_health.php',
  '/psd_logbook/dogs.php',
  '/psd_logbook/backup.php',
  '/psd_logbook/service_dog_rights.php',
  '/psd_logbook/ada_wallet_card.php',
  '/psd_logbook/styles.css',
  '/psd_logbook/alerts.php',
  '/psd_logbook/training_program.php',
  '/psd_logbook/medications.php',
  '/psd_logbook/certification.php',
  '/psd_logbook/offline.html'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
          return response;
        })
        .catch(async () => {
          const cached = await caches.match(request);
          return cached || caches.match('/psd_logbook/offline.html');
        })
    );
    return;
  }

  if (url.origin === location.origin) {
    event.respondWith(
      caches.match(request).then(cached => {
        const fetchPromise = fetch(request).then(response => {
          if (response && response.status === 200) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
          }
          return response;
        }).catch(() => cached);
        return cached || fetchPromise;
      })
    );
  }
});


self.addEventListener('message', (event) => {
  if (!event.data || event.data.type !== 'SHOW_NOTIFICATION') {
    return;
  }
  const payload = event.data.payload || {};
  const title = payload.title || 'PSD Logbook Reminder';
  const options = {
    body: payload.body || '',
    tag: payload.tag || undefined,
    data: payload.data || {},
    renotify: false
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = (event.notification.data && event.notification.data.url) || '/psd_logbook/appointments.php';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client) {
          client.navigate(targetUrl).catch(() => {});
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
      return undefined;
    })
  );
});
