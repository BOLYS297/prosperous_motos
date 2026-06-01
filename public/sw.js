/**
 * Prosperous Motos — Service Worker
 * PWA Offline-First with multi-strategy caching and background sync.
 */

const CACHE_VERSION = 'v1';
const CACHES = {
    pages: `pm-pages-${CACHE_VERSION}`,
    assets: `pm-assets-${CACHE_VERSION}`,
    images: `pm-images-${CACHE_VERSION}`,
    fonts: `pm-fonts-${CACHE_VERSION}`,
};

const ALL_CACHES = Object.values(CACHES);

// Critical pages to pre-cache during install
const PRECACHE_PAGES = [
    '/offline.html',
];

// ─────────────────────────────────────────────
// INSTALL — Pre-cache critical resources
// ─────────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHES.pages).then((cache) => {
            return cache.addAll(PRECACHE_PAGES);
        })
    );
    self.skipWaiting();
});

// ─────────────────────────────────────────────
// ACTIVATE — Clean old caches
// ─────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name.startsWith('pm-') && !ALL_CACHES.includes(name))
                    .map((name) => caches.delete(name))
            );
        }).then(() => self.clients.claim())
    );
});

// ─────────────────────────────────────────────
// FETCH — Route requests to appropriate strategy
// ─────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests (POST forms handled by offline-forms.js on the page)
    if (request.method !== 'GET') {
        return;
    }

    // Skip API requests — handled by SyncManager directly
    if (url.pathname.startsWith('/api/')) {
        return;
    }

    // Skip Vite HMR in dev mode
    if (url.pathname.includes('/@vite') || url.pathname.includes('/__vite')) {
        return;
    }

    // Strategy routing
    if (isAsset(url)) {
        event.respondWith(cacheFirst(request, CACHES.assets));
    } else if (isImage(url)) {
        event.respondWith(cacheFirst(request, CACHES.images));
    } else if (isFont(url)) {
        event.respondWith(cacheFirst(request, CACHES.fonts));
    } else if (isPage(request, url)) {
        event.respondWith(networkFirstPage(request));
    }
});

// ─────────────────────────────────────────────
// URL Classification Helpers
// ─────────────────────────────────────────────
function isAsset(url) {
    return url.pathname.startsWith('/build/') ||
           url.pathname.endsWith('.css') ||
           url.pathname.endsWith('.js');
}

function isImage(url) {
    return url.pathname.match(/\.(png|jpg|jpeg|gif|webp|svg|ico)$/i) ||
           url.pathname.startsWith('/storage/') ||
           url.pathname.startsWith('/produits/');
}

function isFont(url) {
    return url.hostname === 'fonts.googleapis.com' ||
           url.hostname === 'fonts.gstatic.com' ||
           url.hostname === 'cdn.jsdelivr.net' ||
           url.pathname.match(/\.(woff2?|ttf|eot)$/i);
}

function isPage(request, url) {
    return request.mode === 'navigate' ||
           (request.headers.get('accept') || '').includes('text/html');
}

// ─────────────────────────────────────────────
// Cache Strategies
// ─────────────────────────────────────────────

/**
 * Cache First — best for versioned assets, images, fonts.
 * Returns cached version if available, otherwise fetches and caches.
 */
async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        // For images, return a transparent pixel fallback
        if (cacheName === CACHES.images) {
            return new Response(
                'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>',
                { headers: { 'Content-Type': 'image/svg+xml' } }
            );
        }
        return new Response('', { status: 408, statusText: 'Offline' });
    }
}

/**
 * Network First — best for HTML pages.
 * Tries network first, falls back to cache, then offline page.
 */
async function networkFirstPage(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHES.pages);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        // Try cached version
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }

        // Fallback to offline page
        const offlinePage = await caches.match('/offline.html');
        if (offlinePage) {
            return offlinePage;
        }

        return new Response(
            '<html><body><h1>Hors ligne</h1><p>Aucune version en cache disponible.</p></body></html>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 }
        );
    }
}

// ─────────────────────────────────────────────
// MESSAGE — Communication with main thread
// ─────────────────────────────────────────────
self.addEventListener('message', (event) => {
    if (event.data === 'skipWaiting') {
        self.skipWaiting();
    }

    if (event.data === 'getVersion') {
        event.ports[0].postMessage({ version: CACHE_VERSION });
    }
});
