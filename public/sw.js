/**
 * Service Worker — Hub Dashboard
 * Stratégie : Cache First pour les assets statiques, Network First pour les pages
 */

// Incrémenter à chaque changement de stratégie : `activate` purge alors les
// anciens caches, ce qui évite de laisser traîner des réponses obsolètes.
const CACHE_NAME     = 'hub-v3';
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

    // Ne jamais intercepter une autre origine.
    //
    // ⚠️ Sans ce garde-fou, le serveur de dev Vite (http://localhost:5173) était
    // servi en Cache First : son URL /resources/js/app.js ne change JAMAIS alors
    // que son contenu change à chaque modification. Résultat, le navigateur
    // exécutait indéfiniment une version figée du JS, et seul un rechargement
    // forcé (qui contourne le service worker) reflétait le code réel.
    // Les assets de production, eux, sont sur cette origine sous /build/ avec un
    // nom haché : les mettre en cache est sûr, un nouveau build = une nouvelle URL.
    if (url.origin !== self.location.origin) return;

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
