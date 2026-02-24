/**
 * passwordToggle.js - Afficher / masquer le mot de passe
 * Cible tous les boutons .input-password__toggle dans la page
 */

export function initPasswordToggle() {
    document.querySelectorAll('.input-password__toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const wrapper = btn.closest('.input-password');
            const input   = wrapper.querySelector('input');
            const isHidden = input.type === 'password';

            input.type = isHidden ? 'text' : 'password';

            btn.querySelector('.icon-eye').classList.toggle('hidden', !isHidden);
            btn.querySelector('.icon-eye-off').classList.toggle('hidden', isHidden);
            btn.setAttribute('aria-label', isHidden ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });
}
