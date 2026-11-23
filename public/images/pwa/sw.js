/* SMART-DTR service worker — enables install + light offline shell. */
const CACHE_NAME = 'smartdtr-shell-v4';
const PRECACHE_URLS = [
  '/pwa/offline',
  '/images/pwa/icon-192.png',
  '/images/pwa/icon-512.png',
  '/images/pwa/apple-touch-icon.png',
  '/pwa/manifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  if (url.pathname.startsWith('/api/')) {
    return;
  }

  const isDocument = request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html');

  if (isDocument) {
    event.respondWith(
      fetch(request)
        .then((response) => response)
        .catch(() => caches.match('/pwa/offline'))
    );
    return;
  }

  const isStatic = url.pathname.startsWith('/images/')
    || url.pathname.startsWith('/build/')
    || url.pathname.startsWith('/pwa/');

  if (! isStatic) {
    return;
  }

  event.respondWith(
    fetch(request).then((response) => {
      if (response && response.ok) {
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
      }
      return response;
    }).catch(() => caches.match(request))
  );
});
