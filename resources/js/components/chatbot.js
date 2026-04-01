/**
 * chatbot.js – Panneau de chat Cortex IA
 *
 * Ouvre un panneau flottant bottom-right avec iframe vers /chat.
 * Redimensionnable via CSS resize. Bouton expand pour agrandir.
 */

export function initChatbot() {
    const bubble    = document.getElementById('chatbot-bubble');
    if (!bubble) return;

    const overlay   = document.getElementById('chatbot-overlay');
    const win       = document.getElementById('chatbot-window');
    const frame     = document.getElementById('chatbot-frame');
    const loading   = document.getElementById('chatbot-loading');
    const btnClose  = document.getElementById('chatbot-close');
    const btnExpand = document.getElementById('chatbot-expand');
    const btnTab    = document.getElementById('chatbot-open-tab');

    let isLoaded   = false;
    let isExpanded = false;

    function open() {
        overlay.classList.add('is-open');
        bubble.classList.add('is-active');

        if (!isLoaded) {
            frame.src = '/chat?embedded=1';
            frame.addEventListener('load', () => {
                loading.style.display = 'none';
                frame.classList.add('chatbot-frame--visible');
                isLoaded = true;
            }, { once: true });
        }
    }

    function close() {
        overlay.classList.remove('is-open');
        bubble.classList.remove('is-active');
    }

    function toggleExpand() {
        isExpanded = !isExpanded;
        win.classList.toggle('is-expanded', isExpanded);

        // Swap icône expand ↔ shrink
        btnExpand.innerHTML = isExpanded
            ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                 <polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/>
                 <line x1="10" y1="14" x2="3" y2="21"/><line x1="21" y1="3" x2="14" y2="10"/>
               </svg>`
            : `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                 <polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/>
                 <line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>
               </svg>`;
        btnExpand.title = isExpanded ? 'Réduire' : 'Agrandir';
    }

    bubble.addEventListener('click', () => {
        overlay.classList.contains('is-open') ? close() : open();
    });

    btnClose.addEventListener('click', close);
    btnExpand.addEventListener('click', toggleExpand);
    btnTab.addEventListener('click', () => window.open('/chat', '_blank'));

    // Clic en dehors du panneau ferme le panneau
    overlay.addEventListener('click', (e) => {
        if (!e.target.closest('#chatbot-window')) close();
    });

    // Touche Échap ferme aussi
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) close();
    });
}
