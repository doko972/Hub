/**
 * confirmDelete.js
 * Confirmation avant suppression (remplace les confirm() natifs)
 */

export function initConfirmDelete() {
    // Formulaires de suppression avec data-confirm
    document.addEventListener('submit', (e) => {
        const form = e.target;
        const message = form.dataset.confirm;
        if (!message) return;

        if (!window.confirm(message)) {
            e.preventDefault();
        }
    });

    // Liens avec data-confirm
    document.addEventListener('click', (e) => {
        const el = e.target.closest('[data-confirm]');
        if (!el || el.tagName === 'FORM') return;

        const message = el.dataset.confirm;
        if (!window.confirm(message)) {
            e.preventDefault();
        }
    });
}
