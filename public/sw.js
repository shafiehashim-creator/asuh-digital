/*
 * ASUH Digital service worker: lets the site be installed and opened like
 * an app. It keeps only static files (built CSS/JS, fonts, icons) on the
 * device, and shows an offline page when there is no connection. Pages,
 * e-book files, videos and account data are never stored, so access checks
 * and privacy work exactly as on the website.
 */
const VERSION = 'asuh-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/icons/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

// Fingerprinted build files never change, so the copy on the device is always right.
const isStatic = (url) => url.origin === self.location.origin
    && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/fonts/'));

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    // Pages always come from the network; the offline page only when it fails.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));

        return;
    }

    const url = new URL(request.url);
    if (isStatic(url)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(VERSION).then((cache) => cache.put(request, copy));
                }

                return response;
            })),
        );
    }
});
