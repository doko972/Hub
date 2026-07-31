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

    const url = data.url || '/';

    event.waitUntil((async () => {
        // Ne pas notifier quelqu'un qui a déjà la page sous les yeux : il voit
        // le message arriver. Le serveur ne peut pas le savoir, le service
        // worker si.
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const dejaOuvert = windows.some((client) => {
            try {
                return client.focused && new URL(client.url).pathname === url;
            } catch {
                return false;
            }
        });

        if (dejaOuvert) return;

        await self.registration.showNotification(data.title || 'Hub', {
            body:     data.body  || '',
            icon:     data.icon  || '/icon-192x192.png',
            badge:    data.badge || '/icon-192x192.png',
            // tag regroupe les notifications d'un même fil, renotify permet
            // quand même de resignaler l'arrivée d'un nouveau message.
            tag:      data.tag,
            renotify: Boolean(data.renotify && data.tag),
            data:     url,
            vibrate:  [100, 50, 100],
        });
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data || '/';

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        // Réutiliser un onglet déjà ouvert sur le Hub plutôt que d'en empiler
        // un nouveau à chaque notification.
        for (const client of windows) {
            try {
                if (new URL(client.url).origin === self.location.origin) {
                    await client.focus();
                    if (new URL(client.url).pathname !== url && 'navigate' in client) {
                        await client.navigate(url);
                    }
                    return;
                }
            } catch {
                // URL inexploitable : on passe au client suivant.
            }
        }

        await self.clients.openWindow(url);
    })());
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function isStaticAsset(pathname) {
    return /\.(png|jpg|jpeg|gif|svg|ico|woff2?|ttf|css|js|json)$/.test(pathname);
}
