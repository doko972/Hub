/**
 * burger.js
 * Gestion du menu burger + sidebar responsive
 */

export function initBurger() {
    const burger   = document.getElementById('burger');
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebar-overlay');

    if (!burger || !sidebar) return;

    function openSidebar() {
        sidebar.classList.add('is-open');
        burger.classList.add('is-active');
        burger.setAttribute('aria-expanded', 'true');
        if (overlay) {
            overlay.classList.add('is-visible');
        }
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        burger.classList.remove('is-active');
        burger.setAttribute('aria-expanded', 'false');
        if (overlay) {
            overlay.classList.remove('is-visible');
        }
        document.body.style.overflow = '';
    }

    function toggleSidebar() {
        const isOpen = sidebar.classList.contains('is-open');
        isOpen ? closeSidebar() : openSidebar();
    }

    // Clic burger
    burger.addEventListener('click', toggleSidebar);

    // Clic sur l'overlay (ferme la sidebar)
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Touche Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
    });

    // Fermer automatiquement si on passe en mode desktop (resize)
    const mediaQuery = window.matchMedia('(min-width: 1025px)');
    mediaQuery.addEventListener('change', (e) => {
        if (e.matches) {
            closeSidebar();
        }
    });
}
