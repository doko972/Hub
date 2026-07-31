/**
 * toast.js — Notifications visuelles (bottom-right)
 */

const DURATION = 5000;

const ICONS = {
    success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>`,
    error:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
    info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
};

function getContainer() {
    let el = document.getElementById('toast-container');
    if (!el) {
        el = document.createElement('div');
        el.id = 'toast-container';
        el.className = 'toast-container';
        document.body.appendChild(el);
    }
    return el;
}

const CLOSE_ICON = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14">
    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
</svg>`;

/**
 * Affiche un élément déjà construit et gère sa disparition.
 */
function present(toast, duration = DURATION) {
    getContainer().appendChild(toast);

    // Déclenche l'animation d'entrée
    requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('is-visible')));

    const dismiss = () => {
        toast.classList.remove('is-visible');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    };

    const timer = setTimeout(dismiss, duration);

    toast.querySelector('.toast__close')?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        clearTimeout(timer);
        dismiss();
    });

    return dismiss;
}

export function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.setAttribute('role', 'alert');

    const icon = document.createElement('span');
    icon.className = 'toast__icon';
    icon.innerHTML = ICONS[type] ?? ICONS.info;

    // textContent et non innerHTML : les messages flash contiennent des données
    // saisies (nom d'outil, nom d'utilisateur…) et n'ont pas à être interprétées.
    const text = document.createElement('span');
    text.className = 'toast__message';
    text.textContent = message;

    const close = document.createElement('button');
    close.className = 'toast__close';
    close.setAttribute('aria-label', 'Fermer');
    close.innerHTML = CLOSE_ICON;

    toast.append(icon, text, close);
    present(toast);
}

/**
 * Notification discrète d'un nouveau message : une carte cliquable qui mène
 * à la conversation, et qui s'efface d'elle-même.
 *
 * Volontairement pas une modale : elle bloquerait la page et volerait le focus
 * pour une information qui n'appelle aucune décision immédiate.
 */
export function showMessageToast(message) {
    const toast = document.createElement('a');
    toast.className = 'toast toast--message';
    toast.href = message.url;
    toast.setAttribute('role', 'alert');

    const avatar = document.createElement('span');
    avatar.className = 'toast__avatar';

    if (message.avatar) {
        const img = document.createElement('img');
        img.src = message.avatar;
        img.alt = '';
        avatar.appendChild(img);
    } else {
        avatar.textContent = message.initials || '?';
    }

    const body = document.createElement('span');
    body.className = 'toast__body';

    const title = document.createElement('span');
    title.className = 'toast__title';
    // En groupe, le fil ne dit pas qui parle : on l'ajoute.
    title.textContent = message.is_group
        ? `${message.title} — ${message.author}`
        : message.author;

    const excerpt = document.createElement('span');
    excerpt.className = 'toast__excerpt';
    excerpt.textContent = message.excerpt;

    body.append(title, excerpt);

    const close = document.createElement('button');
    close.className = 'toast__close';
    close.setAttribute('aria-label', 'Fermer');
    close.innerHTML = CLOSE_ICON;

    toast.append(avatar, body, close);

    // Un peu plus longtemps qu'un toast ordinaire : il y a du texte à lire.
    present(toast, 8000);
}

export function initToasts() {
    const data = window.__hubFlash || {};
    if (data.success) showToast(data.success, 'success');
    if (data.error)   showToast(data.error,   'error');
    if (data.status)  showToast(data.status,  'info');
    if (data.warning) showToast(data.warning, 'warning');
}
