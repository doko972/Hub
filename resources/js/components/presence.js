/**
 * Panneau « En ligne » de la sidebar.
 *
 * Le premier rendu vient du serveur (partials/sidebar-presence.blade.php) ;
 * ce module ne fait que rafraîchir la liste à intervalle régulier.
 *
 * Les noms sont insérés via textContent, jamais par innerHTML : ce sont des
 * données saisies par un administrateur, mais la règle vaut pour tout contenu
 * venant de la base.
 */

let timer = null;

function buildAvatar(person) {
    const avatar = document.createElement('span');
    avatar.className = 'presence__avatar';

    if (person.avatar) {
        const img = document.createElement('img');
        img.src = person.avatar;
        img.alt = '';
        avatar.appendChild(img);
    } else {
        avatar.appendChild(document.createTextNode(person.initials || ''));
    }

    const dot = document.createElement('span');
    dot.className = 'presence__dot';
    dot.setAttribute('aria-hidden', 'true');
    avatar.appendChild(dot);

    return avatar;
}

function buildBody(person) {
    const body = document.createElement('span');
    body.className = 'presence__body';

    const name = document.createElement('span');
    name.className = 'presence__name';
    name.textContent = person.name;

    if (person.is_self) {
        const self = document.createElement('em');
        self.textContent = ' (vous)';
        name.appendChild(self);
    }

    body.appendChild(name);

    if (!person.is_online && person.seen_ago) {
        const meta = document.createElement('span');
        meta.className = 'presence__meta';
        meta.textContent = person.seen_ago;
        body.appendChild(meta);
    }

    return body;
}

/**
 * Enveloppe de la ligne : un bouton qui ouvre la conversation, ou un simple
 * bloc quand il s'agit de soi.
 *
 * POST et non lien : ouvrir une conversation peut la créer, ce qui n'a pas sa
 * place dans un GET. Doit rester en phase avec partials/presence-row.blade.php.
 */
function buildRow(person, directUrl, csrf) {
    const contenu = [buildAvatar(person), buildBody(person)];

    if (person.is_self || !directUrl || !csrf) {
        const row = document.createElement('span');
        row.className = 'presence__row';
        row.append(...contenu);
        return row;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = directUrl.replace('__ID__', person.id);

    const token = document.createElement('input');
    token.type  = 'hidden';
    token.name  = '_token';
    token.value = csrf;

    const button = document.createElement('button');
    button.type      = 'submit';
    button.className = 'presence__row';
    button.title     = `Écrire à ${person.name}`;
    button.append(...contenu);

    form.append(token, button);

    return form;
}

function buildItem(person, directUrl, csrf) {
    const item = document.createElement('li');
    item.className = 'presence__item' + (person.is_online ? ' is-online' : '');
    item.appendChild(buildRow(person, directUrl, csrf));

    return item;
}

function render(list, data) {
    const fragment = document.createDocumentFragment();

    if (!data.users.length) {
        const empty = document.createElement('li');
        empty.className = 'presence__empty';
        empty.textContent = "Personne d'autre pour l'instant.";
        fragment.appendChild(empty);
    } else {
        const directUrl = list.dataset.directUrl;
        const csrf      = document.querySelector('meta[name="csrf-token"]')?.content;

        data.users.forEach((person) => fragment.appendChild(buildItem(person, directUrl, csrf)));
    }

    list.replaceChildren(fragment);

    const counter = document.querySelector('[data-presence-count]');
    if (counter) counter.textContent = data.online;
}

export function initPresence() {
    const list = document.querySelector('[data-presence-list]');
    if (!list) return;

    const url      = list.dataset.presenceUrl;
    const interval = (parseInt(list.dataset.presenceInterval, 10) || 30) * 1000;

    async function refresh() {
        // Inutile de solliciter le serveur pour un onglet que personne ne regarde.
        if (document.hidden) return;

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) return;

            render(list, await response.json());
        } catch {
            // Coupure réseau : on garde l'affichage précédent et on retentera
            // au prochain tour.
        }
    }

    function start() {
        stop();
        timer = setInterval(refresh, interval);
    }

    function stop() {
        if (timer) clearInterval(timer);
        timer = null;
    }

    // Au retour sur l'onglet, on rafraîchit immédiatement : la liste peut
    // dater de plusieurs minutes.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stop();
        } else {
            refresh();
            start();
        }
    });

    start();
}
