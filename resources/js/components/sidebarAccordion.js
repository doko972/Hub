/**
 * Sections repliables de la sidebar (Outils, Administration).
 *
 * L'état est porté par une classe sur <html> plutôt que sur la section :
 * partials/sidebar-boot.blade.php la pose dès le <head>, ce qui évite qu'une
 * section repliée apparaisse brièvement déployée au chargement.
 */

const STORAGE_KEY = 'hub.sidebar.collapsed';

function readCollapsed() {
    try {
        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY));
        return Array.isArray(stored) ? stored : [];
    } catch {
        return [];
    }
}

function writeCollapsed(keys) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(keys));
    } catch {
        // Navigation privée ou stockage plein : l'accordéon reste utilisable,
        // seule la persistance est perdue.
    }
}

export function initSidebarAccordion() {
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');
    if (!toggles.length) return;

    const root = document.documentElement;

    toggles.forEach((toggle) => {
        const key       = toggle.dataset.sidebarToggle;
        const className = `is-sidebar-collapsed-${key}`;

        // Aligne l'attribut ARIA sur l'état réellement appliqué par le boot.
        toggle.setAttribute('aria-expanded', root.classList.contains(className) ? 'false' : 'true');

        toggle.addEventListener('click', () => {
            const collapsed = root.classList.toggle(className);

            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

            const keys = readCollapsed().filter((k) => k !== key);
            if (collapsed) keys.push(key);
            writeCollapsed(keys);
        });
    });

    // Les transitions ne sont activées qu'après l'application de l'état initial,
    // pour que le premier rendu ne soit pas animé.
    root.classList.add('is-sidebar-ready');
}
