/**
 * theme.js
 * Sélection du thème d'interface.
 *
 * Deux notions à ne pas confondre :
 *   - la PRÉFÉRENCE  : ce que l'utilisateur a choisi ("system", "nord", …)
 *   - le thème RÉSOLU : ce que le CSS applique ("dark", "nord", …)
 * Elles ne diffèrent que lorsque la préférence vaut "system".
 *
 * L'application initiale est faite par partials/theme-boot.blade.php, dans
 * <head>, pour éviter tout clignotement. Ce module ne gère que les
 * changements après chargement.
 *
 * Persistance : localStorage (immédiat, couvre les pages publiques)
 * + enregistrement serveur (retrouve son thème sur un autre appareil).
 */

import { showToast } from './toast.js';

const STORAGE_KEY = 'hub-theme';
const SYSTEM      = 'system';

const systemQuery = window.matchMedia('(prefers-color-scheme: dark)');

// ---- Préférence courante ----
function getPreference() {
    return document.documentElement.getAttribute('data-theme-pref')
        || localStorage.getItem(STORAGE_KEY)
        || SYSTEM;
}

// ---- Préférence → thème réellement appliqué ----
function resolve(pref) {
    if (pref !== SYSTEM) return pref;
    return systemQuery.matches ? 'dark' : 'light';
}

// ---- Appliquer ----
function applyTheme(pref) {
    const el = document.documentElement;
    el.setAttribute('data-theme-pref', pref);
    el.setAttribute('data-theme', resolve(pref));

    syncMetaThemeColor();
    markActiveOption(pref);
}

/**
 * Aligne <meta name="theme-color"> (barre d'état mobile / PWA) sur le thème.
 * On lit le token --theme-color calculé plutôt que de redéclarer les
 * couleurs ici : le SCSS reste la seule source de vérité.
 */
function syncMetaThemeColor() {
    const meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) return;

    const color = getComputedStyle(document.documentElement)
        .getPropertyValue('--theme-color')
        .trim();

    if (color) meta.setAttribute('content', color);
}

// ---- Refléter le choix courant dans les menus et la page préférences ----
function markActiveOption(pref) {
    document.querySelectorAll('[data-theme-choice]').forEach((el) => {
        const isActive = el.dataset.themeChoice === pref;
        el.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        el.classList.toggle('is-active', isActive);
    });
}

// ---- Enregistrer côté serveur ----
//
// Le thème est DÉJÀ appliqué à l'écran quand on arrive ici : seul
// l'enregistrement peut échouer. Il faut donc le dire, sinon le choix
// paraît fonctionner puis disparaît au rechargement — exactement le
// symptôme observé quand la migration de la colonne `theme` manquait.
// `fetch` ne rejette PAS sur une réponse 4xx/5xx : tester `response.ok`
// est indispensable, un simple .catch() ne verrait rien.
function persist(pref) {
    try { localStorage.setItem(STORAGE_KEY, pref); } catch (e) { /* mode privé */ }

    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) return; // page publique : localStorage suffit

    fetch('/preferences/theme', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept':       'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({ theme: pref }),
    })
        .then((response) => {
            if (response.ok) return;

            showToast(
                response.status === 419
                    ? 'Session expirée : reconnectez-vous pour conserver ce thème.'
                    : 'Thème appliqué, mais non enregistré. Il sera perdu au rechargement.',
                'warning'
            );
        })
        .catch(() => {
            showToast('Hors ligne : ce thème ne sera pas conservé.', 'warning');
        });
}

// ---- Initialisation ----
export function initTheme() {
    applyTheme(getPreference());

    // Délégation : couvre le menu navbar ET les cartes de la page préférences,
    // y compris si elles sont rendues après coup.
    document.addEventListener('click', (e) => {
        const choice = e.target.closest('[data-theme-choice]');
        if (!choice) return;

        e.preventDefault();
        const pref = choice.dataset.themeChoice;

        applyTheme(pref);
        persist(pref);
    });

    // Suivre l'appareil tant que l'utilisateur est en "Automatique"
    systemQuery.addEventListener('change', () => {
        if (getPreference() === SYSTEM) applyTheme(SYSTEM);
    });
}
