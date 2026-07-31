/**
 * Activation des notifications push du navigateur.
 *
 * Le bouton reste masqué tant que le navigateur ne sait pas faire (Safari iOS
 * hors écran d'accueil, navigation privée, absence de HTTPS…) : proposer une
 * option inopérante est pire que ne rien proposer.
 */

function isSupported() {
    return 'serviceWorker' in navigator
        && 'PushManager' in window
        && 'Notification' in window;
}

/**
 * La clé VAPID voyage en base64url ; l'API attend un Uint8Array.
 */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = window.atob(base64);

    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function post(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);

    return response.json();
}

export function initPushNotifications() {
    const button = document.querySelector('[data-push-toggle]');
    if (!button) return;

    if (!isSupported() || Notification.permission === 'denied') {
        // Permission refusée : seul l'utilisateur peut revenir en arrière,
        // depuis les réglages du navigateur.
        button.hidden = true;
        return;
    }

    const urls = {
        vapid:       button.dataset.vapidUrl,
        subscribe:   button.dataset.subscribeUrl,
        unsubscribe: button.dataset.unsubscribeUrl,
    };

    let registration = null;

    function paint(active) {
        button.classList.toggle('is-active', active);
        button.textContent = active ? '🔔 Notifications activées' : '🔕 Activer les notifications';
        button.hidden = false;
    }

    async function currentSubscription() {
        registration ??= await navigator.serviceWorker.register('/sw.js');
        await navigator.serviceWorker.ready;

        return registration.pushManager.getSubscription();
    }

    async function enable() {
        if (await Notification.requestPermission() !== 'granted') {
            paint(false);
            return;
        }

        const { public_key: publicKey } = await (await fetch(urls.vapid, {
            headers: { Accept: 'application/json' },
        })).json();

        if (!publicKey) {
            button.textContent = 'Notifications non configurées';
            button.disabled = true;
            return;
        }

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });

        const json = subscription.toJSON();

        await post(urls.subscribe, {
            endpoint:   json.endpoint,
            public_key: json.keys.p256dh,
            auth_token: json.keys.auth,
        });

        paint(true);
    }

    async function disable(subscription) {
        await post(urls.unsubscribe, { endpoint: subscription.endpoint });
        await subscription.unsubscribe();
        paint(false);
    }

    button.addEventListener('click', async () => {
        button.disabled = true;

        try {
            const subscription = await currentSubscription();

            if (subscription) {
                await disable(subscription);
            } else {
                await enable();
            }
        } catch (error) {
            console.error('Notifications push :', error);
            button.textContent = 'Échec — réessayer';
        } finally {
            button.disabled = false;
        }
    });

    // État initial, sans rien demander à l'utilisateur.
    currentSubscription()
        .then((subscription) => paint(Boolean(subscription)))
        .catch(() => { button.hidden = true; });
}
