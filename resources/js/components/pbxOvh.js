/**
 * pbxOvh.js — Import des centrex depuis l'inventaire OVHcloud
 *
 * La modale demande la liste au Hub (qui, lui, parle à OVH et signe les
 * requêtes), puis renvoie les références cochées par une soumission de
 * formulaire classique : le serveur redirige et rejoue le message flash, comme
 * le reste de la page.
 *
 * Rien de ce qui est affiché n'est renvoyé au serveur en dehors de la
 * référence de la machine — noms et IP sont relus côté serveur.
 */

import { showToast } from './toast.js';

export function initPbxOvh() {
    const overlay = document.getElementById('pbx-ovh-overlay');
    if (!overlay) return;

    const form       = document.getElementById('pbx-ovh-form');
    const liste      = document.getElementById('pbx-ovh-list');
    const intro      = document.getElementById('pbx-ovh-intro');
    const notes      = document.getElementById('pbx-ovh-notes');
    const refresh    = document.getElementById('pbx-ovh-refresh');
    const submit     = document.getElementById('pbx-ovh-submit');
    const allWrap    = document.getElementById('pbx-ovh-all-wrap');
    const allBox     = document.getElementById('pbx-ovh-all');
    const filtreWrap = document.getElementById('pbx-ovh-filter-wrap');
    const filtre     = document.getElementById('pbx-ovh-filter');

    // L'inventaire est demandé à la première ouverture seulement : l'appel
    // interroge OVH pour de bon. « Actualiser » force le rechargement.
    let charge = false;
    let requete = 0;

    function ouvrir() {
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');

        if (!charge) charger(false);
    }

    function fermer() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
    }

    async function charger(forcer) {
        const jeton = ++requete;

        charge = true;
        refresh.disabled = true;
        submit.disabled  = true;
        allWrap.hidden   = true;
        filtreWrap.hidden = true;
        notes.hidden     = true;
        notes.innerHTML  = '';
        liste.innerHTML  = '<p class="pbx-ovh__state">Chargement de l\'inventaire OVHcloud…</p>';

        const url = new URL(overlay.dataset.listUrl, window.location.origin);
        if (forcer) url.searchParams.set('refresh', '1');

        try {
            const res = await fetch(url, {
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await res.json().catch(() => ({}));

            if (jeton !== requete) return;

            if (!res.ok) {
                throw new Error(data.message || "L'inventaire OVHcloud n'a pas pu être chargé.");
            }

            afficherNotes(data.notes || []);
            afficher(data.machines || []);
        } catch (e) {
            if (jeton !== requete) return;

            // Une clé refusée ou une source injoignable doit rester réessayable :
            // le bouton « Actualiser » relance sans rouvrir la modale.
            charge = false;
            liste.innerHTML = '';
            liste.append(etat(e.message || "L'inventaire OVHcloud n'a pas pu être chargé.", true));
        } finally {
            if (jeton === requete) refresh.disabled = false;
        }
    }

    function etat(message, erreur = false) {
        const p = document.createElement('p');
        p.className = erreur ? 'pbx-ovh__state pbx-ovh__state--error' : 'pbx-ovh__state';
        p.textContent = message;
        return p;
    }

    /**
     * Sources ou projets en échec. Les afficher plutôt que de rendre une liste
     * silencieusement incomplète.
     */
    function afficherNotes(messages) {
        notes.innerHTML = '';
        notes.hidden = messages.length === 0;

        messages.forEach(message => {
            const p = document.createElement('p');
            p.textContent = message;
            notes.append(p);
        });
    }

    function afficher(machines) {
        liste.innerHTML = '';

        if (!machines.length) {
            liste.append(etat('Aucune machine trouvée sur ce compte OVHcloud.'));
            intro.textContent = '';
            return;
        }

        const importables = machines.filter(m => !m.existing && m.ipv4).length;

        intro.textContent = importables
            ? `${machines.length} machine(s) sur le compte, dont ${importables} pas encore référencée(s).`
            : `${machines.length} machine(s) sur le compte : toutes sont déjà référencées.`;

        const fragment = document.createDocumentFragment();
        machines.forEach(machine => fragment.append(ligne(machine)));
        liste.append(fragment);

        filtreWrap.hidden = machines.length < 10;
        filtre.value = '';
        allWrap.hidden = importables === 0;
        allBox.checked = false;
        majSubmit();
    }

    /**
     * Une ligne par machine. Construite en DOM et non par innerHTML : les noms
     * viennent du manager OVH, donc d'une saisie libre.
     */
    function ligne(machine) {
        const importable = !machine.existing && !!machine.ipv4;

        const label = document.createElement('label');
        label.className = 'pbx-ovh__item' + (importable ? '' : ' is-disabled');

        // Repris tel quel par le filtre : une seule chaîne à comparer.
        label.dataset.recherche = [
            machine.name, machine.ipv4, machine.group, machine.zone, machine.ref,
        ].filter(Boolean).join(' ').toLowerCase();

        const case_ = document.createElement('input');
        case_.type = 'checkbox';
        case_.name = 'machines[]';
        case_.value = machine.ref;
        case_.disabled = !importable;
        label.append(case_);

        const corps = document.createElement('div');
        corps.className = 'pbx-ovh__body';

        const titre = document.createElement('span');
        titre.className = 'pbx-ovh__name';
        titre.textContent = machine.name;
        corps.append(titre);

        const meta = document.createElement('span');
        meta.className = 'pbx-ovh__meta';
        meta.textContent = [
            machine.ipv4 || 'aucune IPv4 publique',
            machine.group,
            machine.zone,
        ].filter(Boolean).join(' · ');
        corps.append(meta);

        label.append(corps);

        const tag = document.createElement('span');
        tag.className = 'pbx-ovh__tag';

        if (machine.existing) {
            tag.textContent = 'Déjà référencé';
            tag.classList.add('is-known');
        } else if (!machine.ipv4) {
            tag.textContent = 'Sans IPv4';
            tag.classList.add('is-blocked');
        } else if (machine.state && !['ACTIVE', 'running'].includes(machine.state)) {
            tag.textContent = machine.state;
            tag.classList.add('is-blocked');
        } else {
            tag.textContent = 'Importable';
            tag.classList.add('is-new');
        }

        label.append(tag);

        return label;
    }

    /**
     * Cases cochables actuellement visibles. Le filtre ne doit pas emporter
     * dans l'import des machines que l'utilisateur ne voit plus.
     */
    function cases() {
        return [...liste.querySelectorAll('.pbx-ovh__item:not([hidden]) input:not(:disabled)')];
    }

    function majSubmit() {
        const coches = [...liste.querySelectorAll('input[name="machines[]"]:checked')].length;

        submit.disabled = coches === 0;
        submit.textContent = coches
            ? `Importer la sélection (${coches})`
            : 'Importer la sélection';
    }

    function appliquerFiltre() {
        const terme = filtre.value.trim().toLowerCase();

        liste.querySelectorAll('.pbx-ovh__item').forEach(item => {
            item.hidden = terme !== '' && !item.dataset.recherche.includes(terme);
        });

        // La case « tout sélectionner » porte sur ce qui est visible : elle ne
        // peut pas rester cochée quand le filtre change le périmètre.
        allBox.checked = false;
    }

    // ---------------------------------------------------------------
    // Écoutes
    // ---------------------------------------------------------------

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-ovh-open]')) ouvrir();
    });

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay || e.target.closest('[data-pbx-close]')) fermer();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) fermer();
    });

    refresh.addEventListener('click', () => charger(true));

    liste.addEventListener('change', majSubmit);

    filtre.addEventListener('input', appliquerFiltre);

    allBox.addEventListener('change', () => {
        cases().forEach(c => { c.checked = allBox.checked; });
        majSubmit();
    });

    form.addEventListener('submit', (e) => {
        if (liste.querySelector('input[name="machines[]"]:checked')) {
            submit.disabled = true;
            submit.textContent = 'Import en cours…';
            return;
        }

        e.preventDefault();
        showToast('Sélectionnez au moins une machine à importer.', 'warning');
    });
}
