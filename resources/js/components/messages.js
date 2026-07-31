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

import { showMessageToast, showToast } from './toast.js';
import { attachEmojiPicker } from './emojiPicker.js';
import { QUICK_REACTIONS } from '../emoji-data.js';

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

    if (message.body) {
        const body = document.createElement('p');
        body.className = 'bubble__body';
        body.textContent = message.body;
        content.appendChild(body);
    }

    if (message.attachments?.length) {
        content.appendChild(buildAttachments(message.attachments));
    }

    const time = document.createElement('span');
    time.className = 'bubble__time';
    time.textContent = message.sent_at;

    if (message.edited) {
        const edited = document.createElement('em');
        edited.className = 'bubble__edited';
        edited.textContent = ' · modifié';
        time.appendChild(edited);
    }

    const reactions = document.createElement('div');
    reactions.className = 'reactions';
    reactions.dataset.reactions = '';

    content.append(time, reactions);
    bubble.appendChild(content);

    if (message.is_mine) {
        const actions = document.createElement('div');
        actions.className = 'bubble__actions';

        if (message.body) {
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.dataset.editTrigger = '';
            edit.title = 'Modifier';
            edit.setAttribute('aria-label', 'Modifier');
            edit.textContent = '✏️';
            actions.appendChild(edit);
        }

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.deleteTrigger = '';
        remove.title = 'Supprimer';
        remove.setAttribute('aria-label', 'Supprimer');
        remove.textContent = '🗑';
        actions.appendChild(remove);

        bubble.appendChild(actions);
    }

    const react = document.createElement('button');
    react.type = 'button';
    react.className = 'bubble__react';
    react.dataset.reactTrigger = '';
    react.setAttribute('aria-label', 'Réagir');
    react.textContent = '🙂';
    bubble.appendChild(react);

    if (message.reactions?.length) {
        renderReactions(reactions, message.reactions);
    }

    return bubble;
}

/**
 * (Re)dessine la ligne de réactions d'un message.
 * Miroir de messages/partials/bubble.blade.php.
 */
function renderReactions(container, reactions) {
    container.replaceChildren();

    reactions.forEach((reaction) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'reaction' + (reaction.mine ? ' is-mine' : '');
        button.dataset.emoji = reaction.emoji;

        const emoji = document.createElement('span');
        emoji.className = 'reaction__emoji';
        emoji.textContent = reaction.emoji;

        const count = document.createElement('span');
        count.className = 'reaction__count';
        count.textContent = reaction.count;

        button.append(emoji, count);
        container.appendChild(button);
    });
}

// Miroir de messages/partials/bubble.blade.php : garder les deux en phase.
function buildAttachments(attachments) {
    const wrapper = document.createElement('div');
    wrapper.className = 'attachments';

    attachments.forEach((file) => {
        const link = document.createElement('a');
        link.href = file.url;

        if (file.is_image) {
            link.className = 'attachments__image';
            link.target = '_blank';
            link.rel = 'noopener';

            const img = document.createElement('img');
            img.src = file.url;
            img.alt = file.name;
            img.loading = 'lazy';
            link.appendChild(img);
        } else {
            link.className = 'attachments__file';

            const name = document.createElement('span');
            name.className = 'attachments__name';
            name.textContent = file.name;

            const size = document.createElement('span');
            size.className = 'attachments__size';
            size.textContent = file.size;

            link.append(name, size);
        }

        wrapper.appendChild(link);
    });

    return wrapper;
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

    // ---- Fichiers en attente d'envoi ----
    const fileInput = composer.querySelector('[data-file-input]');
    const fileList  = composer.querySelector('[data-file-list]');
    const maxFiles  = parseInt(composer.dataset.maxFiles, 10) || 5;
    const maxBytes  = (parseInt(composer.dataset.maxSizeKb, 10) || 10240) * 1024;
    let pending     = [];

    function humanSize(bytes) {
        if (bytes < 1024) return `${bytes} o`;
        if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} Ko`;
        return `${(bytes / 1024 / 1024).toFixed(1)} Mo`;
    }

    function renderFiles() {
        fileList.replaceChildren();
        fileList.hidden = pending.length === 0;

        pending.forEach((file, index) => {
            const item = document.createElement('li');
            item.className = 'composer-files__item';

            const name = document.createElement('span');
            name.textContent = file.name;

            const size = document.createElement('span');
            size.className = 'composer-files__size';
            size.textContent = humanSize(file.size);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'composer-files__remove';
            remove.setAttribute('aria-label', `Retirer ${file.name}`);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                pending.splice(index, 1);
                renderFiles();
            });

            item.append(name, size, remove);
            fileList.appendChild(item);
        });
    }

    function clearFiles() {
        pending = [];
        fileInput.value = '';
        renderFiles();
    }

    fileInput?.addEventListener('change', () => {
        // Contrôles côté client pour un retour immédiat ; le serveur revalide
        // de toute façon, c'est lui qui fait foi.
        for (const file of fileInput.files) {
            if (pending.length >= maxFiles) {
                showToast(`Pas plus de ${maxFiles} fichiers par message.`, 'warning');
                break;
            }

            if (file.size > maxBytes) {
                showToast(`« ${file.name} » dépasse ${humanSize(maxBytes)}.`, 'warning');
                continue;
            }

            pending.push(file);
        }

        fileInput.value = '';
        renderFiles();
    });

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

    // ---- Modification et suppression de ses propres messages ----
    const messageUrlTemplate = container.dataset.messageUrl;

    function messageUrl(id) {
        return messageUrlTemplate.replace('__ID__', id);
    }

    function replaceBubble(id, payload) {
        const ancienne = container.querySelector(`[data-message-id="${id}"]`);
        if (ancienne) ancienne.replaceWith(buildBubble(payload));
    }

    function removeBubble(id) {
        container.querySelector(`[data-message-id="${id}"]`)?.remove();
    }

    container.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-edit-trigger]');
        if (!trigger) return;

        const bubble = trigger.closest('[data-message-id]');
        const corps  = bubble.querySelector('.bubble__body');
        if (!corps || bubble.querySelector('.bubble__edit')) return;

        const original = corps.textContent;

        // Édition en place : le champ remplace le texte, la bulle reste à sa
        // position dans le fil.
        const form = document.createElement('form');
        form.className = 'bubble__edit';

        const champ = document.createElement('textarea');
        champ.value = original;
        champ.rows = 2;
        champ.maxLength = 5000;

        const actions = document.createElement('div');
        actions.className = 'bubble__edit-actions';

        const valider = document.createElement('button');
        valider.type = 'submit';
        valider.textContent = 'Enregistrer';

        const annuler = document.createElement('button');
        annuler.type = 'button';
        annuler.textContent = 'Annuler';

        actions.append(annuler, valider);
        form.append(champ, actions);
        corps.replaceWith(form);
        champ.focus();
        champ.setSelectionRange(champ.value.length, champ.value.length);

        function restore(texte) {
            const p = document.createElement('p');
            p.className = 'bubble__body';
            p.textContent = texte;
            form.replaceWith(p);
        }

        annuler.addEventListener('click', () => restore(original));

        // Échap annule, Entrée valide — mêmes réflexes que le composeur.
        champ.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') restore(original);
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.requestSubmit();
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const nouveau = champ.value.trim();
            if (!nouveau || nouveau === original) return restore(original);

            valider.disabled = true;

            try {
                const response = await fetch(messageUrl(bubble.dataset.messageId), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ body: nouveau }),
                });

                if (!response.ok) throw new Error();

                replaceBubble(bubble.dataset.messageId, (await response.json()).message);
            } catch {
                restore(original);
                showToast('La modification a échoué.', 'error');
            }
        });
    });

    container.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-delete-trigger]');
        if (!trigger) return;

        if (!window.confirm('Supprimer ce message ? Les pièces jointes seront également effacées.')) return;

        const bubble = trigger.closest('[data-message-id]');
        trigger.disabled = true;

        try {
            const response = await fetch(messageUrl(bubble.dataset.messageId), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            if (!response.ok) throw new Error();

            removeBubble(bubble.dataset.messageId);
        } catch {
            trigger.disabled = false;
            showToast('La suppression a échoué.', 'error');
        }
    });

    // ---- Réactions ----
    const reactionUrlTemplate = container.dataset.reactionUrl;

    async function toggleReaction(messageId, emoji) {
        try {
            const response = await fetch(reactionUrlTemplate.replace('__ID__', messageId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ emoji }),
            });

            if (!response.ok) return;

            const { reactions } = await response.json();
            const bubble = container.querySelector(`[data-message-id="${messageId}"] [data-reactions]`);

            if (bubble) renderReactions(bubble, reactions);
        } catch {
            // Sans réponse, le sondage rétablira l'état réel.
        }
    }

    /**
     * Applique l'état renvoyé par le sondage : les réactions posées par les
     * autres n'arrivent pas avec les nouveaux messages, puisqu'elles portent
     * sur des messages déjà affichés.
     */
    function applyReactions(map) {
        container.querySelectorAll('[data-message-id]').forEach((bubble) => {
            const row = bubble.querySelector('[data-reactions]');
            if (!row) return;

            renderReactions(row, map?.[bubble.dataset.messageId] ?? []);
        });
    }

    // Clic sur une réaction existante : bascule.
    container.addEventListener('click', (event) => {
        const reaction = event.target.closest('.reaction');
        if (!reaction) return;

        const bubble = reaction.closest('[data-message-id]');
        if (bubble) toggleReaction(bubble.dataset.messageId, reaction.dataset.emoji);
    });

    // Clic sur le bouton « réagir » : raccourcis les plus courants.
    let quickPanel = null;

    container.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-react-trigger]');
        if (!trigger) return;

        event.stopPropagation();
        quickPanel?.remove();

        const bubble = trigger.closest('[data-message-id]');
        quickPanel = document.createElement('div');
        quickPanel.className = 'quick-reactions';

        QUICK_REACTIONS.forEach((emoji) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = emoji;
            button.addEventListener('click', () => {
                toggleReaction(bubble.dataset.messageId, emoji);
                quickPanel.remove();
                quickPanel = null;
            });
            quickPanel.appendChild(button);
        });

        trigger.parentElement.appendChild(quickPanel);
    });

    document.addEventListener('click', (event) => {
        if (quickPanel && !quickPanel.contains(event.target)) {
            quickPanel.remove();
            quickPanel = null;
        }
    });

    async function poll() {
        if (document.hidden || polling) return;
        polling = true;

        try {
            const response = await fetch(`${pollUrl}?after=${lastId}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (response.ok) {
                const data = await response.json();
                append(data.messages);
                applyReactions(data.reactions);

                // Modifications et suppressions faites par d'autres : elles ne
                // peuvent pas arriver par la liste des nouveaux messages.
                Object.entries(data.edited ?? {}).forEach(([id, payload]) => {
                    const bulle = container.querySelector(`[data-message-id="${id}"]`);

                    // On ne réécrit pas une bulle en cours d'édition chez soi.
                    if (bulle && !bulle.querySelector('.bubble__edit')) {
                        replaceBubble(id, payload);
                    }
                });

                (data.deleted ?? []).forEach(removeBubble);
            }
        } catch {
            // Coupure réseau : on retentera au tour suivant.
        } finally {
            polling = false;
        }
    }

    async function send() {
        const body = textarea.value.trim();

        // Un message peut n'être qu'une pièce jointe.
        if (!body && !pending.length) return;

        const button = composer.querySelector('button[type="submit"]');
        button.disabled = true;

        // multipart plutôt que JSON : c'est le seul format qui transporte des
        // fichiers. Le corps du message voyage dans le même envoi.
        const form = new FormData();
        form.append('body', body);
        pending.forEach((file) => form.append('attachments[]', file));

        try {
            const response = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: form,
            });

            if (!response.ok) {
                const erreur = response.status === 422
                    ? Object.values((await response.json()).errors ?? {})[0]?.[0]
                    : null;

                throw new Error(erreur || '');
            }

            const { message } = await response.json();
            append([message]);

            textarea.value = '';
            textarea.style.height = 'auto';
            clearFiles();
            container.scrollTop = container.scrollHeight;
        } catch (error) {
            // Le contenu reste en place : l'utilisateur peut corriger et réessayer.
            button.textContent = error.message || 'Échec — réessayer';
            setTimeout(() => { button.textContent = 'Envoyer'; }, 3500);
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

    // ---- Émoticones dans le message ----
    const emojiToggle = composer.querySelector('[data-emoji-toggle]');

    if (emojiToggle) {
        attachEmojiPicker(emojiToggle, (emoji) => {
            // Insertion à la position du curseur plutôt qu'en fin de champ.
            const debut = textarea.selectionStart ?? textarea.value.length;
            const fin   = textarea.selectionEnd ?? textarea.value.length;

            textarea.value = textarea.value.slice(0, debut) + emoji + textarea.value.slice(fin);
            textarea.selectionStart = textarea.selectionEnd = debut + emoji.length;
            textarea.focus();
        });
    }

    // ---- Panneau GIF ----
    initGifPanel(composer, (message) => {
        append([message]);
        container.scrollTop = container.scrollHeight;
    });

    setInterval(poll, interval);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
    });
}

/**
 * Recherche et envoi de GIF. La requête passe par notre serveur : la clé Tenor
 * n'est jamais exposée et les recherches ne partent pas du navigateur.
 */
function initGifPanel(composer, onSent) {
    const panel  = composer.querySelector('[data-gif-panel]');
    const toggle = composer.querySelector('[data-gif-toggle]');
    if (!panel || !toggle) return;

    const input   = panel.querySelector('[data-gif-search]');
    const results = panel.querySelector('[data-gif-results]');
    let debounce  = null;

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        panel.hidden = !panel.hidden;
        if (!panel.hidden) input.focus();
    });

    document.addEventListener('click', (event) => {
        if (!panel.hidden && !panel.contains(event.target) && event.target !== toggle) {
            panel.hidden = true;
        }
    });

    async function search(query) {
        results.replaceChildren();

        if (query.trim().length < 2) return;

        const attente = document.createElement('p');
        attente.className = 'gif-panel__status';
        attente.textContent = 'Recherche…';
        results.appendChild(attente);

        try {
            const response = await fetch(`${panel.dataset.searchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            const { gifs } = await response.json();
            results.replaceChildren();

            if (!gifs.length) {
                const vide = document.createElement('p');
                vide.className = 'gif-panel__status';
                vide.textContent = 'Aucun résultat.';
                results.appendChild(vide);
                return;
            }

            gifs.forEach((gif) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'gif-panel__item';

                const img = document.createElement('img');
                img.src = gif.preview;
                img.alt = gif.description;
                img.loading = 'lazy';

                button.appendChild(img);
                button.addEventListener('click', () => send(gif));
                results.appendChild(button);
            });
        } catch {
            results.replaceChildren();
            const erreur = document.createElement('p');
            erreur.className = 'gif-panel__status';
            erreur.textContent = 'Recherche indisponible.';
            results.appendChild(erreur);
        }
    }

    async function send(gif) {
        panel.hidden = true;

        try {
            const response = await fetch(panel.dataset.sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ url: gif.url, description: gif.description }),
            });

            if (!response.ok) throw new Error();

            onSent((await response.json()).message);
        } catch {
            showToast("Impossible d'envoyer ce GIF.", 'error');
        }
    }

    input.addEventListener('input', () => {
        clearTimeout(debounce);
        // Laisse le temps de finir de taper : une requête par frappe saturerait
        // la limitation de débit.
        debounce = setTimeout(() => search(input.value), 400);
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

    // Dernier message déjà signalé. À l'ouverture du Hub, les messages en
    // attente relèvent de la pastille, pas d'une notification : seul ce qui
    // arrive pendant la session mérite un toast.
    let lastNotifiedId = 0;
    let primed         = false;

    function maybeNotify(latest) {
        // L'amorçage doit avoir lieu au premier sondage abouti, y compris
        // lorsque rien n'est en attente. Sans ce drapeau distinct, une boîte
        // vide au chargement laissait « lastNotifiedId » à sa valeur initiale,
        // et le tout premier message reçu était pris pour l'amorce — donc
        // jamais signalé.
        if (!primed) {
            primed = true;
            lastNotifiedId = latest?.id ?? 0;
            return;
        }

        if (!latest || latest.id <= lastNotifiedId) return;

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
