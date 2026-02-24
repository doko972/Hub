/**
 * theme.js
 * Gestion du switch dark / light mode
 *
 * Priorité :
 *   1. Préférence sauvegardée dans localStorage
 *   2. Préférence système (prefers-color-scheme)
 *   3. Défaut : light
 */

const STORAGE_KEY = 'hub-theme';
const DARK        = 'dark';
const LIGHT       = 'light';

// ---- Appliquer un thème ----
function applyTheme(theme) {
    if (theme === DARK) {
        document.documentElement.setAttribute('data-theme', DARK);
    } else {
        document.documentElement.removeAttribute('data-theme');
    }
}

// ---- Lire la préférence courante ----
function getCurrentTheme() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) return saved;
    // Détection système
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? DARK : LIGHT;
}

// ---- Basculer le thème ----
function toggleTheme() {
    const current = getCurrentTheme();
    const next    = current === DARK ? LIGHT : DARK;
    localStorage.setItem(STORAGE_KEY, next);
    applyTheme(next);
    updateToggleButton(next);
}

// ---- Mettre à jour l'icône du bouton ----
function updateToggleButton(theme) {
    const btn      = document.getElementById('theme-toggle');
    const iconSun  = document.getElementById('theme-icon-sun');
    const iconMoon = document.getElementById('theme-icon-moon');
    if (!btn) return;

    if (theme === DARK) {
        // Mode sombre actif → montrer l'icône soleil (pour revenir au clair)
        iconSun?.classList.remove('hidden');
        iconMoon?.classList.add('hidden');
        btn.setAttribute('aria-label', 'Passer en mode clair');
        btn.setAttribute('title', 'Passer en mode clair');
    } else {
        // Mode clair actif → montrer l'icône lune (pour passer au sombre)
        iconSun?.classList.add('hidden');
        iconMoon?.classList.remove('hidden');
        btn.setAttribute('aria-label', 'Passer en mode sombre');
        btn.setAttribute('title', 'Passer en mode sombre');
    }
}

// ---- Initialisation ----
export function initTheme() {
    const theme = getCurrentTheme();

    // Appliquer immédiatement (évite le flash)
    applyTheme(theme);

    // Une fois le DOM prêt, brancher le bouton
    const btn = document.getElementById('theme-toggle');
    if (btn) {
        updateToggleButton(theme);
        btn.addEventListener('click', toggleTheme);
    }

    // Écouter les changements de préférence système
    // (uniquement si l'utilisateur n'a pas de préférence sauvegardée)
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (!localStorage.getItem(STORAGE_KEY)) {
            const sysTheme = e.matches ? DARK : LIGHT;
            applyTheme(sysTheme);
            updateToggleButton(sysTheme);
        }
    });
}

// ---- Application du thème AVANT le rendu (anti-flash) ----
// Ce code s'exécute immédiatement, hors DOMContentLoaded
(function () {
    const saved = localStorage.getItem('hub-theme');
    const sys   = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    if ((saved || sys) === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
})();
