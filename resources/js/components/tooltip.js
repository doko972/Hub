/**
 * tooltip.js
 * Tooltip mobile pour les vignettes (tap = affiche la description)
 * Sur desktop le tooltip est géré en CSS pur (:hover)
 */

export function initTooltips() {
    // Sur mobile, le hover CSS ne fonctionne pas bien.
    // Un tap sur la vignette affiche le tooltip, un second tap ouvre le lien.
    if (!isTouchDevice()) return;

    const tiles = document.querySelectorAll('.tile');

    tiles.forEach((tile) => {
        const tooltip = tile.querySelector('.tile__tooltip');
        if (!tooltip) return;

        let tapped = false;

        tile.addEventListener('click', (e) => {
            if (!tapped) {
                // Premier tap : affiche le tooltip, annule la navigation
                e.preventDefault();
                tapped = true;
                tooltip.style.opacity = '1';
                tooltip.style.transform = 'translateX(-50%) translateY(0)';
                tooltip.style.pointerEvents = 'all';

                // Reset après 3 secondes
                setTimeout(() => {
                    resetTooltip(tooltip);
                    tapped = false;
                }, 3000);
            }
            // Second tap : laisse le lien fonctionner normalement
        });
    });

    // Fermer les tooltips si on tape ailleurs
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.tile')) {
            document.querySelectorAll('.tile__tooltip').forEach(resetTooltip);
            tiles.forEach((t) => {
                t._tapped = false;
            });
        }
    });

    function resetTooltip(tooltip) {
        tooltip.style.opacity = '';
        tooltip.style.transform = '';
        tooltip.style.pointerEvents = '';
    }
}

function isTouchDevice() {
    return window.matchMedia('(hover: none) and (pointer: coarse)').matches;
}
