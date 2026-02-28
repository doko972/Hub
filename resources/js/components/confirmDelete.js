/**
 * confirmDelete.js
 * Modal de confirmation personnalisée (remplace window.confirm)
 * Les formulaires de suppression utilisent data-confirm="message"
 */

let overlay = null;
let pendingForm = null;

function buildModal() {
    overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = `
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
            <div class="confirm-modal__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <p class="confirm-modal__title" id="confirm-title">Confirmer la suppression</p>
            <p class="confirm-modal__message" id="confirm-message"></p>
            <div class="confirm-modal__actions">
                <button class="btn btn--secondary" id="confirm-cancel">Annuler</button>
                <button class="btn btn--danger"    id="confirm-ok">Supprimer</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    overlay.querySelector('#confirm-cancel').addEventListener('click', closeModal);
    overlay.querySelector('#confirm-ok').addEventListener('click', () => {
        closeModal();
        if (pendingForm) pendingForm.submit();
    });

    // Fermeture au clic sur le fond
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal();
    });
}

function openModal(message, form) {
    pendingForm = form;
    overlay.querySelector('#confirm-message').textContent = message;
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    overlay.querySelector('#confirm-cancel').focus();
}

function closeModal() {
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    pendingForm = null;
}

export function initConfirmDelete() {
    buildModal();

    // Fermeture à la touche Échap
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeModal();
    });

    // Interception des soumissions de formulaires data-confirm
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form.dataset.confirm) return;
        e.preventDefault();
        openModal(form.dataset.confirm, form);
    });

    // Interception des liens data-confirm (cas rare)
    document.addEventListener('click', (e) => {
        const el = e.target.closest('[data-confirm]:not(form)');
        if (!el) return;
        e.preventDefault();
        openModal(el.dataset.confirm, null);
    });
}
