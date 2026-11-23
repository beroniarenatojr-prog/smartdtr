<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

class PwaController extends Controller
{
    public function ping(): Response
    {
        return response("pwa-ok\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function manifest(): Response
    {
        $path = resource_path('pwa/manifest.webmanifest');
        $body = is_file($path) ? file_get_contents($path) : $this->fallbackManifest();

        return response($body, 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function serviceWorker(): Response
    {
        $path = resource_path('pwa/sw.js');
        $body = is_file($path) ? file_get_contents($path) : $this->fallbackServiceWorker();

        return response($body, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function offline(): Response
    {
        $path = resource_path('pwa/offline.html');
        $body = is_file($path) ? file_get_contents($path) : $this->fallbackOffline();

        return response($body, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private function fallbackManifest(): string
    {
        return <<<'JSON'
{
  "name": "SMART-DTR — INHS",
  "short_name": "SMART-DTR",
  "description": "Smart Daily Time Record for DepEd Senior High School — INHS.",
  "start_url": "/",
  "scope": "/",
  "display": "standalone",
  "orientation": "any",
  "background_color": "#F5F7FA",
  "theme_color": "#0D47A1",
  "icons": [
    {
      "src": "/images/pwa/icon-192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any"
    },
    {
      "src": "/images/pwa/icon-512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any"
    },
    {
      "src": "/images/pwa/icon-192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "maskable"
    },
    {
      "src": "/images/pwa/icon-512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "maskable"
    }
  ]
}
JSON;
    }

    private function fallbackServiceWorker(): string
    {
        return <<<'JS'
const CACHE_NAME = 'smartdtr-shell-v4';
const PRECACHE_URLS = [
  '/pwa/offline',
  '/images/pwa/icon-192.png',
  '/images/pwa/icon-512.png',
  '/images/pwa/apple-touch-icon.png',
  '/pwa/manifest'
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
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.startsWith('/api/')) return;

  const isDocument = request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html');
  if (isDocument) {
    event.respondWith(
      fetch(request).then((response) => response).catch(() => caches.match('/pwa/offline'))
    );
    return;
  }

  const isStatic = url.pathname.startsWith('/images/')
    || url.pathname.startsWith('/build/')
    || url.pathname.startsWith('/pwa/');

  if (!isStatic) return;

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
JS;
    }

    private function fallbackOffline(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0D47A1">
    <title>Offline — SMART-DTR</title>
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:"Segoe UI",system-ui,sans-serif; background:#F5F7FA; color:#263238; padding:24px; text-align:center; }
        .card { max-width:360px; background:#fff; border-radius:16px; padding:28px 24px; border:1px solid rgba(13,71,161,.12); }
        h1 { margin:0 0 8px; font-size:1.25rem; color:#0D47A1; }
        p { margin:0 0 20px; color:#546E7A; line-height:1.5; font-size:.95rem; }
        button { appearance:none; border:0; background:#0D47A1; color:#fff; font-weight:600; padding:10px 18px; border-radius:10px; cursor:pointer; }
    </style>
</head>
<body>
    <div class="card">
        <h1>You are offline</h1>
        <p>SMART-DTR needs an internet connection. Reconnect, then try again.</p>
        <button type="button" onclick="location.reload()">Try again</button>
    </div>
</body>
</html>
HTML;
    }
}
