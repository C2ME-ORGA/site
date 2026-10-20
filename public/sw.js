const VERSION = 'c2me-v1';
const STATIC_CACHE = `${VERSION}-static`;
const PAGE_CACHE = `${VERSION}-pages`;
const OFFLINE_URL = '/offline.html';

const PRECACHE = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/js/c2me-pwa.js',
    '/js/field-mode.js',
    '/images/icon-192.png',
    '/images/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(STATIC_CACHE);
            await cache.addAll(PRECACHE);
            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(
                keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key)),
            );
            await self.clients.claim();
        })(),
    );
});

const isCacheableAsset = (pathname) =>
    pathname.startsWith('/build/') ||
    pathname.startsWith('/css/') ||
    pathname.startsWith('/js/') ||
    pathname.startsWith('/fonts/') ||
    pathname.startsWith('/images/');

const isApiRequest = (pathname) =>
    pathname.startsWith('/terrain/snapshot') || pathname.startsWith('/terrain/sync');

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (isApiRequest(url.pathname)) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigation(request));
        return;
    }

    if (isCacheableAsset(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request));
    }
});

async function handleNavigation(request) {
    const url = new URL(request.url);
    const shouldCache = url.pathname === '/terrain';

    try {
        const response = await fetch(request);

        if (shouldCache && response.ok) {
            const cache = await caches.open(PAGE_CACHE);
            await cache.put(request, response.clone());
        }

        return response;
    } catch {
        if (shouldCache) {
            const cached = await caches.match(request);

            if (cached) {
                return cached;
            }
        }

        const offline = await caches.match(OFFLINE_URL);

        if (offline) {
            return offline;
        }

        return new Response('Hors ligne', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        });
    }
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request);

    const network = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => null);

    if (cached) {
        await network;
        return cached;
    }

    const response = await network;

    return response ?? new Response('', { status: 504 });
}
