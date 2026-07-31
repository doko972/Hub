/**
 * Messagerie interne : envoi, réception par sondage, modale de création.
 *
 * Choix de transport : sondage plutôt que SSE. Un flux SSE monopoliserait un
 * process PHP par personne connectée pendant toute la durée de sa session, ce
 * qui saturerait php-fpm bien avant d'atteindre une charge intéressante.
 *
 * Tout le contenu venant du serveur est inséré via textContent : un message
 * est du texte rédigé par un utilisateur, jamais du HTML.
 */

import { showMessageToast } from './toast.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// ---- Rendu d'une bulle (miroir de messages/partials/bubble.blade.php) ----
function buildBubble(message) {
    const bubble = document.createElement('div');
    bubble.className = 'bubble' + (message.is_mine ? ' is-mine' : '');
    bubble.dataset.messageId = message.id;

    if (!message.is_mine) {
        const avatar = document.createElement('span');
        avatar.className = 'bubble__avatar';

        if (message.avatar) {
            const img = document.createElement('img');
            img.src = message.avatar;
            img.alt = '';
            avatar.appendChild(img);
        } else {
            avatar.textContent = message.initials;
        }

        bubble.appendChild(avatar);
    }

    const content = document.createElement('div');
    content.className = 'bubble__content';

    if (!message.is_mine) {
        const author = document.createElement('span');
        author.className = 'bubble__author';
        author.textContent = message.author;
        content.appendChild(author);
    }

    const body = document.createElement('p');
    body.className = 'bubble__body';
    body.textContent = message.body;

    const time = document.createElement('span');
    time.className = 'bubble__time';
    time.textContent = message.sent_at;

    content.append(body, time);
    bubble.appendChild(content);

    return bubble;
}

function isScrolledToBottom(container) {
    return container.scrollHeight - container.scrollTop - container.clientHeight < 60;
}

function initThread() {
    const container = document.querySelector('[data-messages]');
    const composer  = document.querySelector('[data-composer]');
    if (!container || !composer) return;

    const pollUrl  = container.dataset.pollUrl;
    const sendUrl  = container.dataset.sendUrl;
    const interval = (parseInt(container.dataset.pollInterval, 10) || 5) * 1000;
    const textarea = composer.querySelector('textarea');
    let lastId     = parseInt(container.dataset.lastId, 10) || 0;
    let polling    = false;

    container.scrollTop = container.scrollHeight;

    function append(messages) {
        if (!messages.length) return;

        // On ne recolle en bas que si l'utilisateur y était déjà : sinon on le
        // sortirait de la portion d'historique qu'il est en train de lire.
        const stick = isScrolledToBottom(container);

        messages.forEach((message) => {
            if (message.id > lastId) lastId = message.id;
            container.appendChild(buildBubble(message));
        });

        container.dataset.lastId = lastId;
        if (stick) container.scrollTop = container.scrollHeight;
    }

    async function poll() {
        if (document.hidden || polling) return;
        polling = true;

        try {
            const response = await fetch(`${pollUrl}?after=${lastId}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (response.ok) {
                append((await response.json()).messages);
            }
        } catch {
            // Coupure réseau : on retentera au tour suivant.
        } finally {
            polling = false;
        }
    }

    async function send() {
        const body = textarea.value.trim();
        if (!body) return;

        const button = composer.querySelector('button[type="submit"]');
        button.disabled = true;

        try {
            const response = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ body }),
            });

            if (!response.ok) throw new Error();

            const { message } = await response.json();
            append([message]);

            textarea.value = '';
            textarea.style.height = 'auto';
            container.scrollTop = container.scrollHeight;
        } catch {
            // Le texte reste dans le champ : l'utilisateur peut réessayer.
            button.textContent = 'Échec — réessayer';
            setTimeout(() => { button.textContent = 'Envoyer'; }, 2500);
        } finally {
            button.disabled = false;
            textarea.focus();
        }
    }

    composer.addEventListener('submit', (event) => {
        event.preventDefault();
        send();
    });

    // Entrée envoie, Maj+Entrée passe à la ligne.
    textarea.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            send();
        }
    });

    // Le champ grandit avec le texte, dans la limite de quelques lignes.
    textarea.addEventListener('input', () => {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 140) + 'px';
    });

    setInterval(poll, interval);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
    });
}

// ---- Modale « Nouvelle discussion » ----
function initModal() {
    const opener = document.querySelector('[data-new-discussion]');
    const modal  = document.querySelector('[data-new-discussion-modal]');
    if (!opener || !modal) return;

    const open  = () => { modal.hidden = false; };
    const close = () => { modal.hidden = true; };

    opener.addEventListener('click', open);

    modal.addEventListener('click', (event) => {
        if (event.target === modal || event.target.closest('[data-close-modal]')) close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) close();
    });

    modal.querySelectorAll('[data-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;

            modal.querySelectorAll('[data-tab]').forEach((t) => t.classList.toggle('is-active', t === tab));
            modal.querySelectorAll('[data-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.panel !== target;
            });
        });
    });
}

// ---- Pastille de non-lus + notification discrète ----
function initUnreadBadge() {
    const badge = document.querySelector('[data-unread-badge]');
    if (!badge) return;

    const interval = (parseInt(badge.dataset.unreadInterval, 10) || 20) * 1000;

    // Dernier message déjà signalé. Amorcé au premier appel sans rien afficher :
    // à l'ouverture du Hub, les messages en attente relèvent de la pastille, pas
    // d'une notification. Seul ce qui arrive pendant la session mérite un toast.
    let lastNotifiedId = null;

    function maybeNotify(latest) {
        if (!latest) return;

        if (lastNotifiedId === null) {
            lastNotifiedId = latest.id;
            return;
        }

        if (latest.id <= lastNotifiedId) return;

        lastNotifiedId = latest.id;

        // Inutile de signaler une conversation qu'on a déjà sous les yeux.
        if (window.location.pathname === latest.url) return;

        showMessageToast(latest);
    }

    async function refresh() {
        if (document.hidden) return;

        try {
            const response = await fetch(badge.dataset.unreadUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) return;

            const { total, latest } = await response.json();

            badge.textContent = total > 99 ? '99+' : total;
            badge.hidden = total === 0;

            maybeNotify(latest);
        } catch {
            // Sans réponse, on conserve la dernière valeur connue.
        }
    }

    refresh();
    setInterval(refresh, interval);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
}

export function initMessages() {
    initThread();
    initModal();
    initUnreadBadge();
}
