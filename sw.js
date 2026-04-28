const CACHE_NAME = 'psd-logbook-v16';
const APP_SHELL = [
  '/',
  '/index.php',
  '/login.php',
  '/register.php',
  '/log_entry.php',
  '/view_logs.php',
  '/settings.php',
  '/profile.php',
  '/manifest.json',
  '/app.js',
  '/csrf_token.php',
  '/appointment_notifications.php',
  '/appointments.php',
  '/dog_health.php',
  '/dogs.php',
  '/backup.php',
  '/service_dog_rights.php',
  '/ada_wallet_card.php',
  '/styles.css',
  '/alerts.php',
  '/training_program.php',
  '/medications.php',
  '/certification.php',
  '/offline.html'
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
          return cached || caches.match('/offline.html');
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
  const targetUrl = (event.notification.data && event.notification.data.url) || '/appointments.php';
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
