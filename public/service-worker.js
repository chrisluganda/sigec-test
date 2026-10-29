const CACHE_NAME = 'sigec-static-v12';
const OFFLINE_PAGE = '/offline.html';
const STATIC_PREFIX = '/build/assets/';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) =>
                cache.addAll([
                    OFFLINE_PAGE,
                    '/offline-visits.html',
                    '/offline-visits.js',
                ]),
            )
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith('sigec-') && key !== CACHE_NAME,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches
                    .match(request)
                    .then((cached) => cached || caches.match(OFFLINE_PAGE)),
            ),
        );
        return;
    }

    if (
        url.pathname.startsWith(STATIC_PREFIX) ||
        [
            '/favicon.svg',
            '/favicon.ico',
            '/manifest.webmanifest',
            '/offline-visits.js',
        ].includes(url.pathname)
    ) {
        event.respondWith(
            caches.open(CACHE_NAME).then(async (cache) => {
                const cached = await cache.match(request);
                if (cached) return cached;
                const response = await fetch(request);
                if (response.ok) await cache.put(request, response.clone());
                return response;
            }),
        );
    }
});
