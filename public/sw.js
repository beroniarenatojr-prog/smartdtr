/* SMART-DTR service worker — enables install + light offline shell. */
const CACHE_NAME = 'smartdtr-shell-v1';
const PRECACHE_URLS = [
  '/offline.html',
  '/images/pwa/icon-192.png',
  '/images/pwa/icon-512.png',
  '/images/pwa/apple-touch-icon.png',
  '/manifest.webmanifest',
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

  // Never cache authenticated API or form posts (GET-only here already).
  if (url.pathname.startsWith('/api/')) {
    return;
  }

  const isDocument = request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html');

  if (isDocument) {
    event.respondWith(
      fetch(request)
        .then((response) => response)
        .catch(() => caches.match('/offline.html'))
    );
    return;
  }

  const isStatic = url.pathname.startsWith('/images/')
    || url.pathname.startsWith('/build/')
    || url.pathname === '/manifest.webmanifest';

  if (! isStatic) {
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const fetched = fetch(request).then((response) => {
        if (response && response.ok) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        }
        return response;
      }).catch(() => cached);

      return cached || fetched;
    })
  );
});
