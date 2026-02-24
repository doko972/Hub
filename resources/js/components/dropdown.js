/**
 * dropdown.js
 * Gestion des menus déroulants (user menu, etc.)
 */

export function initDropdowns() {
    const triggers = document.querySelectorAll('[data-dropdown]');

    triggers.forEach((trigger) => {
        const targetId = trigger.dataset.dropdown;
        const menu = document.getElementById(targetId);

        if (!menu) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = menu.classList.contains('is-open');

            // Fermer tous les autres dropdowns d'abord
            closeAll();

            if (!isOpen) {
                menu.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // Clic en dehors : ferme tout
    document.addEventListener('click', closeAll);

    // Escape : ferme tout
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeAll();
    });

    function closeAll() {
        document.querySelectorAll('.dropdown-menu.is-open').forEach((menu) => {
            menu.classList.remove('is-open');
        });
        triggers.forEach((t) => t.setAttribute('aria-expanded', 'false'));
    }
}
