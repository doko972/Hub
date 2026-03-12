import { showToast } from './toast.js';

export function initCredentials() {
    const keys = document.querySelectorAll('.tile__key');
    if (!keys.length) return;

    // --- Construction de la modale ---
    const overlay = document.createElement('div');
    overlay.className = 'cred-overlay';
    overlay.innerHTML = `
        <div class="cred-modal" role="dialog" aria-modal="true" aria-labelledby="cred-title">
            <div class="cred-modal__header">
                <div class="cred-modal__tool-name" id="cred-title"></div>
                <button class="cred-modal__close" id="cred-close" aria-label="Fermer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <div class="cred-modal__body">

                <div class="cred-field">
                    <label class="cred-field__label" for="cred-login">Identifiant</label>
                    <div class="cred-field__wrap">
                        <input type="text" id="cred-login" class="cred-field__input" placeholder="email ou nom d'utilisateur" autocomplete="off">
                        <button type="button" class="cred-field__copy" data-target="cred-login" title="Copier">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                                <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="cred-field">
                    <label class="cred-field__label" for="cred-password">Mot de passe</label>
                    <div class="cred-field__wrap">
                        <input type="password" id="cred-password" class="cred-field__input" placeholder="••••••••" autocomplete="new-password">
                        <button type="button" class="cred-field__eye" id="cred-eye" title="Afficher">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                        <button type="button" class="cred-field__copy" data-target="cred-password" title="Copier">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                                <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Bookmarklet : visible uniquement si des credentials sont sauvegardés --}}
                <div class="cred-bookmarklet" id="cred-bookmarklet-wrap" style="display:none;">
                    <div class="cred-bookmarklet__label">Auto-remplissage</div>
                    <div class="cred-bookmarklet__row">
                        <a id="cred-bookmarklet-link" class="cred-bookmarklet__link" href="javascript:void(0)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
                                <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                            </svg>
                            Remplir le formulaire
                        </a>
                        <span class="cred-bookmarklet__hint">← Glissez ce bouton vers vos favoris</span>
                    </div>
                </div>

            </div>

            <div class="cred-modal__footer">
                <div class="cred-modal__footer-left">
                    <button type="button" class="btn btn--danger btn--sm" id="cred-delete" style="display:none;">
                        Effacer
                    </button>
                </div>
                <div class="cred-modal__footer-right">
                    <a href="#" target="_blank" rel="noopener" class="btn btn--ghost btn--sm" id="cred-open">
                        Ouvrir le site
                    </a>
                    <button type="button" class="btn btn--primary btn--sm" id="cred-save">
                        Sauvegarder
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    const titleEl        = overlay.querySelector('#cred-title');
    const loginInput     = overlay.querySelector('#cred-login');
    const passInput      = overlay.querySelector('#cred-password');
    const eyeBtn         = overlay.querySelector('#cred-eye');
    const saveBtn        = overlay.querySelector('#cred-save');
    const deleteBtn      = overlay.querySelector('#cred-delete');
    const openBtn        = overlay.querySelector('#cred-open');
    const closeBtn       = overlay.querySelector('#cred-close');
    const bookmarkletWrap = overlay.querySelector('#cred-bookmarklet-wrap');
    const bookmarkletLink = overlay.querySelector('#cred-bookmarklet-link');

    let currentToolId = null;
    let currentKeyBtn = null;

    // --- Génère le href javascript: du bookmarklet ---
    function buildBookmarklet(login, password) {
        const l = login.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        const p = password.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        // Essaie une liste de sélecteurs courants pour le champ login
        return `javascript:(function(){var l='${l}';var p='${p}';var s=['input[type="email"]','input[name="email"]','input[name="login"]','input[name="username"]','input[name="user"]','input[id*="email"]','input[id*="login"]','input[id*="user"]','input[id*="username"]'];var lf=null;for(var i=0;i<s.length;i++){lf=document.querySelector(s[i]);if(lf)break;}var pf=document.querySelector('input[type="password"]');function fill(el,v){el.value=v;['input','change'].forEach(function(e){el.dispatchEvent(new Event(e,{bubbles:true}));});}if(lf)fill(lf,l);if(pf)fill(pf,p);})();`;
    }

    // --- Ouverture ---
    function openModal(btn) {
        currentToolId = btn.dataset.toolId;
        currentKeyBtn = btn;

        const login    = btn.dataset.login    || '';
        const password = btn.dataset.password || '';

        titleEl.textContent    = btn.dataset.toolName;
        loginInput.value       = login;
        passInput.value        = password;
        openBtn.href           = btn.dataset.toolUrl;
        passInput.type         = 'password';

        const hasCreds = !!(login || password);
        deleteBtn.style.display       = hasCreds ? '' : 'none';
        bookmarkletWrap.style.display = hasCreds ? '' : 'none';

        if (hasCreds) {
            bookmarkletLink.href = buildBookmarklet(login, password);
        }

        overlay.classList.add('is-open');
        setTimeout(() => loginInput.focus(), 50);
    }

    function closeModal() {
        overlay.classList.remove('is-open');
        currentToolId = null;
        currentKeyBtn = null;
    }

    // --- Fermeture ---
    closeBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeModal();
    });

    // --- Ouvrir le site + copie auto du mot de passe ---
    openBtn.addEventListener('click', () => {
        if (passInput.value) {
            navigator.clipboard.writeText(passInput.value).then(() => {
                showToast('Mot de passe copié ! Collez-le dans le champ correspondant.', 'info');
            });
        }
        // L'ouverture dans un nouvel onglet est gérée par href + target="_blank"
    });

    // --- Afficher/masquer le mot de passe ---
    eyeBtn.addEventListener('click', () => {
        const isHidden = passInput.type === 'password';
        passInput.type = isHidden ? 'text' : 'password';
        eyeBtn.title   = isHidden ? 'Masquer' : 'Afficher';
    });

    // --- Copier un champ ---
    overlay.addEventListener('click', (e) => {
        const btn = e.target.closest('.cred-field__copy');
        if (!btn) return;
        const input = overlay.querySelector('#' + btn.dataset.target);
        if (!input || !input.value) {
            showToast('Aucune valeur à copier.', 'warning');
            return;
        }
        navigator.clipboard.writeText(input.value).then(() => {
            showToast('Copié dans le presse-papier !', 'success');
        });
    });

    // --- Sauvegarder ---
    saveBtn.addEventListener('click', async () => {
        saveBtn.disabled    = true;
        saveBtn.textContent = 'Sauvegarde...';

        try {
            const res = await fetch(`/credentials/${currentToolId}`, {
                method: 'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ login: loginInput.value, password: passInput.value }),
            });

            if (!res.ok) throw new Error();

            // Mise à jour du DOM
            currentKeyBtn.dataset.login    = loginInput.value;
            currentKeyBtn.dataset.password = passInput.value;
            currentKeyBtn.classList.add('tile__key--saved');

            showToast('Identifiants sauvegardés.', 'success');
            closeModal();

        } catch {
            showToast('Erreur lors de la sauvegarde.', 'error');
        } finally {
            saveBtn.disabled    = false;
            saveBtn.textContent = 'Sauvegarder';
        }
    });

    // --- Effacer ---
    deleteBtn.addEventListener('click', async () => {
        deleteBtn.disabled    = true;
        deleteBtn.textContent = 'Suppression...';

        try {
            const res = await fetch(`/credentials/${currentToolId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!res.ok) throw new Error();

            currentKeyBtn.dataset.login    = '';
            currentKeyBtn.dataset.password = '';
            currentKeyBtn.classList.remove('tile__key--saved');

            showToast('Identifiants supprimés.', 'success');
            closeModal();

        } catch {
            showToast('Erreur lors de la suppression.', 'error');
        } finally {
            deleteBtn.disabled    = false;
            deleteBtn.textContent = 'Effacer';
        }
    });

    // --- Écoute des boutons clé ---
    keys.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            openModal(btn);
        });
    });
}
