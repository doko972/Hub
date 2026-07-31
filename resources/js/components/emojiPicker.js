/**
 * Sélecteur d'émoticones réutilisable.
 *
 * Sert à la fois au composeur (insertion dans le texte) et aux réactions
 * (choix libre au-delà des raccourcis proposés).
 */

import { EMOJI_CATEGORIES } from '../emoji-data.js';

const RECENTS_KEY = 'hub.emoji.recents';
const RECENTS_MAX = 24;

function readRecents() {
    try {
        const stored = JSON.parse(localStorage.getItem(RECENTS_KEY));
        return Array.isArray(stored) ? stored : [];
    } catch {
        return [];
    }
}

function rememberEmoji(emoji) {
    const recents = [emoji, ...readRecents().filter((e) => e !== emoji)].slice(0, RECENTS_MAX);

    try {
        localStorage.setItem(RECENTS_KEY, JSON.stringify(recents));
    } catch {
        // Stockage indisponible : les récents ne survivront pas à la session.
    }
}

/**
 * Construit un panneau d'émoticones détaché du DOM.
 *
 * @param {(emoji: string) => void} onSelect
 * @returns {{element: HTMLElement, refresh: () => void}}
 */
export function createEmojiPicker(onSelect) {
    const panel = document.createElement('div');
    panel.className = 'emoji-picker';

    const tabs = document.createElement('div');
    tabs.className = 'emoji-picker__tabs';
    tabs.setAttribute('role', 'tablist');

    const grid = document.createElement('div');
    grid.className = 'emoji-picker__grid';

    let activeId = 'smileys';

    function categoryEmojis(category) {
        return category.id === 'frequent' ? readRecents() : category.emojis;
    }

    function renderGrid() {
        const category = EMOJI_CATEGORIES.find((c) => c.id === activeId);
        const emojis   = categoryEmojis(category);

        grid.replaceChildren();

        if (!emojis.length) {
            const vide = document.createElement('p');
            vide.className = 'emoji-picker__empty';
            vide.textContent = 'Aucune émoticone récente.';
            grid.appendChild(vide);
            return;
        }

        emojis.forEach((emoji) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'emoji-picker__emoji';
            button.textContent = emoji;
            button.setAttribute('aria-label', emoji);
            button.addEventListener('click', () => {
                rememberEmoji(emoji);
                onSelect(emoji);
            });
            grid.appendChild(button);
        });
    }

    EMOJI_CATEGORIES.forEach((category) => {
        const tab = document.createElement('button');
        tab.type = 'button';
        tab.className = 'emoji-picker__tab';
        tab.textContent = category.icon;
        tab.title = category.label;
        tab.setAttribute('role', 'tab');
        tab.classList.toggle('is-active', category.id === activeId);

        tab.addEventListener('click', () => {
            activeId = category.id;
            tabs.querySelectorAll('.emoji-picker__tab').forEach((t) => t.classList.toggle('is-active', t === tab));
            renderGrid();
        });

        tabs.appendChild(tab);
    });

    panel.append(tabs, grid);
    renderGrid();

    return {
        element: panel,
        refresh: renderGrid,
    };
}

/**
 * Attache un panneau à un bouton déclencheur, avec fermeture au clic extérieur
 * et à la touche Échap.
 */
export function attachEmojiPicker(trigger, onSelect) {
    const wrapper = document.createElement('div');
    wrapper.className = 'emoji-popover';
    wrapper.hidden = true;

    const picker = createEmojiPicker((emoji) => {
        onSelect(emoji);
        close();
    });

    wrapper.appendChild(picker.element);
    trigger.parentElement.appendChild(wrapper);

    function open() {
        picker.refresh();
        wrapper.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
    }

    function close() {
        wrapper.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }

    trigger.setAttribute('aria-expanded', 'false');

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        wrapper.hidden ? open() : close();
    });

    document.addEventListener('click', (event) => {
        if (!wrapper.hidden && !wrapper.contains(event.target) && event.target !== trigger) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !wrapper.hidden) close();
    });

    return { open, close };
}
