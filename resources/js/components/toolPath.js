/**
 * Vignettes pointant vers un chemin réseau.
 *
 * Les navigateurs refusent d'ouvrir un lien file:// ou UNC déclenché depuis une
 * page https — silencieusement, sans message. Plutôt qu'une vignette morte, on
 * copie le chemin : l'utilisateur le colle dans l'explorateur.
 */

import { showToast } from './toast.js';

export function initToolPaths() {
    const vignettes = document.querySelectorAll('[data-copy-path]');
    if (!vignettes.length) return;

    vignettes.forEach((vignette) => {
        vignette.addEventListener('click', async () => {
            const chemin = vignette.dataset.copyPath;

            try {
                await navigator.clipboard.writeText(chemin);
                showToast('Chemin copié : collez-le dans l\'explorateur de fichiers.', 'success');
            } catch {
                // Presse-papier refusé (contexte non sécurisé, permission) :
                // on affiche le chemin pour qu'il reste copiable à la main.
                window.prompt('Copiez ce chemin :', chemin);
            }
        });
    });
}
