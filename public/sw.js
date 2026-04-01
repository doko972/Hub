/**
 * Service Worker — Hub Dashboard
 * Stratégie : Cache First pour les assets statiques, Network First pour les pages
 */

const CACHE_NAME     = 'hub-v2';
const ASSETS_TO_CACHE = [
    '/',
    '/chat',
    '/manifest.json',
    '/icon-192x192.png',
    '/icon-512x512.png',
    '/favicon.ico',
];

// ── Installation : mise en cache des assets de base ──────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(ASSETS_TO_CACHE))
            .then(() => self.skipWaiting())
    );
});

// ── Activation : suppression des anciens caches ───────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(key => key !== CACHE_NAME)
                    .map(key => caches.delete(key))
            )
        ).then(() => self.clients.claim())
    );
});

// ── Fetch : Network First pour les pages, Cache First pour les assets ─────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Ignorer les requêtes non-GET et les API
    if (request.method !== 'GET') return;
    if (url.pathname.startsWith('/api/')) return;

    // Assets statiques (build Vite) → Cache First
    if (url.pathname.startsWith('/build/') || isStaticAsset(url.pathname)) {
        event.respondWith(
            caches.match(request).then(cached =>
                cached || fetch(request).then(response => {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
                    return response;
                })
            )
        );
        return;
    }

    // Pages → Network First, fallback cache
    event.respondWith(
        fetch(request)
            .then(response => {
                const clone = response.clone();
                caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
                return response;
            })
            .catch(() => caches.match(request))
    );
});

// ── Notifications Push ────────────────────────────────────────────────────────
self.addEventListener('push', (event) => {
    if (!event.data) return;

    let data = {};
    try { data = event.data.json(); } catch { data = { title: 'Hub', body: event.data.text() }; }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Hub', {
            body:    data.body    || '',
            icon:    data.icon    || '/icon-192x192.png',
            badge:   data.badge   || '/icon-192x192.png',
            data:    data.url     || '/dashboard',
            vibrate: [100, 50, 100],
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        clients.openWindow(event.notification.data || '/dashboard')
    );
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function isStaticAsset(pathname) {
    return /\.(png|jpg|jpeg|gif|svg|ico|woff2?|ttf|css|js|json)$/.test(pathname);
}
