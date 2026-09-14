/**
 * pbx.js — Annuaire des centrex FreePBX
 *
 * Deux modales : le formulaire d'ajout/modification (soumission classique,
 * le serveur redirige et rejoue les messages flash) et la fiche d'identifiants,
 * qui va chercher le mot de passe déchiffré au moment de l'ouverture. Les
 * secrets ne sont jamais présents dans le HTML de la liste, et sont effacés du
 * DOM à la fermeture.
 */

import { showToast } from './toast.js';

export function initPbx() {
    const formOverlay = document.getElementById('pbx-form-overlay');
    if (!formOverlay) return;

    const secretOverlay = document.getElementById('pbx-secret-overlay');
    const form          = document.getElementById('pbx-form');
    const methodInput   = document.getElementById('pbx-method');
    const idInput       = document.getElementById('pbx_id');
    const titleEl       = document.getElementById('pbx-form-title');
    const submitBtn     = document.getElementById('pbx-submit');
    const passwordHint  = document.getElementById('pbx-password-hint');
    const clearWrap     = document.getElementById('pbx-clear-wrap');
    const clearCheckbox = clearWrap.querySelector('input');

    const secretHost   = document.getElementById('pbx-secret-host');
    const secretTitle  = document.getElementById('pbx-secret-title');
    const secretLogin  = document.getElementById('pbx-secret-login');
    const secretPass   = document.getElementById('pbx-secret-password');
    const secretEye    = document.getElementById('pbx-secret-eye');
    const secretOpen   = document.getElementById('pbx-secret-open');

    const field = (name) => form.querySelector(`[name="${name}"]`);

    /**
     * Fiche embarquée dans la carte (tout sauf le mot de passe).
     * Un attribut illisible doit se voir : sans ce garde-fou, l'exception
     * laissait simplement le bouton sans effet.
     */
    function ficheDe(card) {
        try {
            return JSON.parse(card.dataset.pbx);
        } catch {
            showToast('Fiche illisible : rechargez la page.', 'error');
            return null;
        }
    }

    // Jeton de requête : si l'utilisateur ouvre une deuxième fiche pendant le
    // chargement de la première, la réponse en retard est ignorée.
    let secretRequest = 0;

    // ---------------------------------------------------------------
    // Formulaire
    // ---------------------------------------------------------------

    function openForm(fiche) {
        if (fiche) {
            titleEl.textContent   = 'Modifier le centrex';
            submitBtn.textContent = 'Enregistrer les modifications';

            form.action        = form.dataset.updateUrl.replace('__ID__', fiche.id);
            methodInput.value  = 'PUT';
            methodInput.disabled = false;
            idInput.value      = fiche.id;

            field('name').value     = fiche.name   ?? '';
            field('client').value   = fiche.client ?? '';
            field('protocol').value = fiche.protocol ?? 'http';
            field('host').value     = fiche.host   ?? '';
            field('port').value     = fiche.port   ?? '';
            field('path').value     = fiche.path   ?? '';
            field('login').value    = fiche.login  ?? '';
            field('notes').value    = fiche.notes  ?? '';
            document.getElementById('pbx-active').checked = !!fiche.is_active;

            // Le mot de passe existant n'est pas renvoyé au navigateur : champ
            // vide = « ne pas y toucher ».
            field('password').value = '';
            passwordHint.hidden = !fiche.hasPassword;
            clearWrap.hidden    = !fiche.hasPassword;
            clearCheckbox.checked = false;
        } else {
            titleEl.textContent   = 'Nouveau centrex';
            submitBtn.textContent = 'Ajouter le centrex';

            form.action          = form.dataset.storeUrl;
            methodInput.disabled = true;
            idInput.value        = '';

            // Vidage explicite plutôt que form.reset() : après une erreur de
            // validation, les valeurs par défaut du HTML sont celles de la
            // saisie fautive, que reset() remettrait en place.
            ['name', 'client', 'host', 'port', 'path', 'login', 'password', 'notes']
                .forEach(nom => { field(nom).value = ''; });

            field('protocol').value = 'http';
            clearCheckbox.checked   = false;
            document.getElementById('pbx-active').checked = true;

            passwordHint.hidden = true;
            clearWrap.hidden    = true;
        }

        openOverlay(formOverlay);
        setTimeout(() => field('name').focus(), 50);
    }

    // ---------------------------------------------------------------
    // Identifiants
    // ---------------------------------------------------------------

    async function openSecret(card, url) {
        const fiche = ficheDe(card);
        if (!fiche) return;

        const jeton = ++secretRequest;

        secretTitle.textContent = fiche.name;
        secretHost.textContent  = fiche.port ? `${fiche.host}:${fiche.port}` : fiche.host;
        secretLogin.value       = '';
        secretPass.value        = '';
        secretPass.type         = 'password';
        secretOpen.href         = card.querySelector('a[target="_blank"]').href;

        openOverlay(secretOverlay);

        try {
            const res = await fetch(url, {
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!res.ok) throw new Error();

            const { login, password } = await res.json();

            if (jeton !== secretRequest) return;

            secretLogin.value = login    || '';
            secretPass.value  = password || '';

            if (!login && !password) {
                showToast('Aucun identifiant enregistré pour ce centrex.', 'warning');
            }
        } catch {
            if (jeton === secretRequest) {
                showToast('Impossible de charger les identifiants.', 'error');
            }
        }
    }

    // ---------------------------------------------------------------
    // Redémarrage de la machine
    // ---------------------------------------------------------------

    /**
     * Redémarre la machine OVH d'un centrex.
     *
     * Confirmation explicite : l'action coupe les communications en cours du
     * client. Le bouton est ensuite neutralisé le temps de l'appel, pour qu'un
     * double-clic n'envoie pas deux redémarrages.
     */
    async function redemarrer(bouton) {
        const nom = bouton.dataset.name || 'ce centrex';

        if (!window.confirm(
            `Redémarrer la machine de « ${nom} » ?\n\n`
            + 'Les communications en cours sur ce centrex seront coupées.'
        )) {
            return;
        }

        const libelle = bouton.innerHTML;
        bouton.disabled = true;
        bouton.textContent = 'Redémarrage…';

        try {
            const res = await fetch(bouton.dataset.url, {
                method: 'POST',
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) throw new Error(data.message || 'Le redémarrage a été refusé.');

            showToast(data.message || 'Redémarrage demandé.', 'success');
        } catch (e) {
            showToast(e.message || 'Le redémarrage a échoué.', 'error');
        } finally {
            bouton.disabled = false;
            bouton.innerHTML = libelle;
        }
    }

    // ---------------------------------------------------------------
    // Ouverture / fermeture des modales
    // ---------------------------------------------------------------

    function openOverlay(overlay) {
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
    }

    function closeOverlay(overlay) {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');

        // Ne rien laisser traîner dans le DOM après fermeture.
        if (overlay === secretOverlay) {
            secretRequest++;
            secretLogin.value = '';
            secretPass.value  = '';
            secretPass.type   = 'password';
        }
    }

    function closeAll() {
        [formOverlay, secretOverlay].forEach(closeOverlay);
    }

    // ---------------------------------------------------------------
    // Écoutes
    // ---------------------------------------------------------------

    document.addEventListener('click', (e) => {
        const nouveau = e.target.closest('[data-pbx-new]');
        if (nouveau) {
            openForm(null);
            return;
        }

        const edition = e.target.closest('[data-pbx-edit]');
        if (edition) {
            const fiche = ficheDe(edition.closest('[data-pbx]'));
            if (fiche) openForm(fiche);
            return;
        }

        const secret = e.target.closest('[data-pbx-secret]');
        if (secret) {
            openSecret(secret.closest('[data-pbx]'), secret.dataset.url);
            return;
        }

        const reboot = e.target.closest('[data-pbx-reboot]');
        if (reboot) {
            redemarrer(reboot);
            return;
        }

        if (e.target.closest('[data-pbx-close]')) {
            closeAll();
            return;
        }

        // Copie de l'adresse depuis une carte
        const copie = e.target.closest('[data-pbx-copy]');
        if (copie) {
            copier(copie.dataset.pbxCopy, 'Adresse copiée.');
            return;
        }

        // Copie d'un champ de la fiche d'identifiants
        const copieChamp = e.target.closest('[data-pbx-copy-field]');
        if (copieChamp) {
            const input = document.getElementById(copieChamp.dataset.pbxCopyField);
            copier(input?.value, 'Copié dans le presse-papier.');
        }
    });

    // Clic sur le voile : ferme la modale concernée, pas les deux.
    [formOverlay, secretOverlay].forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeOverlay(overlay);
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            [formOverlay, secretOverlay]
                .filter(o => o.classList.contains('is-open'))
                .forEach(closeOverlay);
        }
    });

    secretEye.addEventListener('click', () => {
        const masque = secretPass.type === 'password';
        secretPass.type = masque ? 'text' : 'password';
        secretEye.title = masque ? 'Masquer' : 'Afficher';
    });

    function copier(valeur, message) {
        if (!valeur) {
            showToast('Aucune valeur à copier.', 'warning');
            return;
        }
        navigator.clipboard.writeText(valeur)
            .then(() => showToast(message, 'success'))
            .catch(() => showToast('Copie impossible depuis ce navigateur.', 'error'));
    }

    // ---------------------------------------------------------------
    // Réouverture après une erreur de validation
    //
    // Le serveur a renvoyé la page avec les anciennes valeurs déjà en place ;
    // il reste à replacer le formulaire en mode édition le cas échéant.
    // ---------------------------------------------------------------
    if (formOverlay.classList.contains('is-open') && idInput.value) {
        titleEl.textContent   = 'Modifier le centrex';
        submitBtn.textContent = 'Enregistrer les modifications';
        form.action           = form.dataset.updateUrl.replace('__ID__', idInput.value);
        methodInput.value     = 'PUT';
        methodInput.disabled  = false;
        passwordHint.hidden   = false;
        clearWrap.hidden      = false;
    }
}
