/**
 * Fenêtre de nouveau message.
 *
 * Choisie après essai en équipe : la carte discrète en bas d'écran passait
 * inaperçue au milieu d'une page de travail. Une fenêtre au centre demande un
 * geste pour disparaître, ce qui est précisément l'effet recherché.
 *
 * Deux garde-fous pour que l'interruption reste supportable :
 *   — une seule fenêtre à la fois, mise à jour si d'autres messages arrivent ;
 *   — aucune navigation automatique, c'est l'utilisateur qui décide.
 */

let overlay        = null;
let elementActif   = null;
let messagesEnAttente = 0;

function build() {
    const racine = document.createElement('div');
    racine.className = 'message-modal';
    racine.hidden = true;

    const panneau = document.createElement('div');
    panneau.className = 'message-modal__panel';
    panneau.setAttribute('role', 'dialog');
    panneau.setAttribute('aria-modal', 'true');
    panneau.setAttribute('aria-labelledby', 'message-modal-title');

    const entete = document.createElement('div');
    entete.className = 'message-modal__header';

    const avatar = document.createElement('span');
    avatar.className = 'message-modal__avatar';

    const titres = document.createElement('div');

    const titre = document.createElement('p');
    titre.className = 'message-modal__title';
    titre.id = 'message-modal-title';

    const sousTitre = document.createElement('p');
    sousTitre.className = 'message-modal__subtitle';
    sousTitre.textContent = 'Nouveau message';

    titres.append(sousTitre, titre);
    entete.append(avatar, titres);

    const extrait = document.createElement('p');
    extrait.className = 'message-modal__excerpt';

    const compteur = document.createElement('p');
    compteur.className = 'message-modal__more';
    compteur.hidden = true;

    const actions = document.createElement('div');
    actions.className = 'message-modal__actions';

    const plusTard = document.createElement('button');
    plusTard.type = 'button';
    plusTard.className = 'btn btn--ghost btn--sm';
    plusTard.textContent = 'Plus tard';

    const ouvrir = document.createElement('a');
    ouvrir.className = 'btn btn--primary btn--sm';
    ouvrir.textContent = 'Ouvrir la conversation';

    actions.append(plusTard, ouvrir);
    panneau.append(entete, extrait, compteur, actions);
    racine.appendChild(panneau);
    document.body.appendChild(racine);

    plusTard.addEventListener('click', close);
    racine.addEventListener('click', (event) => {
        if (event.target === racine) close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !racine.hidden) close();
    });

    return { racine, avatar, titre, extrait, compteur, ouvrir, plusTard };
}

function close() {
    if (!overlay) return;

    overlay.racine.hidden = true;
    messagesEnAttente = 0;
    overlay.compteur.hidden = true;

    // Rendre le focus là où il était : l'utilisateur reprend son travail où il
    // l'avait laissé.
    elementActif?.focus?.();
    elementActif = null;
}

export function showMessageModal(message) {
    overlay ??= build();

    const dejaOuverte = !overlay.racine.hidden;

    // Plusieurs messages pendant que la fenêtre est ouverte : on ne l'empile
    // pas, on signale simplement qu'il y en a d'autres.
    if (dejaOuverte) {
        messagesEnAttente += 1;
        overlay.compteur.hidden = false;
        overlay.compteur.textContent = messagesEnAttente === 1
            ? '+ 1 autre message reçu'
            : `+ ${messagesEnAttente} autres messages reçus`;
    } else {
        elementActif = document.activeElement;
        messagesEnAttente = 0;
        overlay.compteur.hidden = true;
    }

    overlay.avatar.replaceChildren();

    if (message.avatar) {
        const img = document.createElement('img');
        img.src = message.avatar;
        img.alt = '';
        overlay.avatar.appendChild(img);
    } else {
        overlay.avatar.textContent = message.initials || '?';
    }

    overlay.titre.textContent = message.is_group
        ? `${message.title} — ${message.author}`
        : message.author;

    overlay.extrait.textContent = message.excerpt;
    overlay.ouvrir.href = message.url;

    if (!dejaOuverte) {
        overlay.racine.hidden = false;
        // Le focus part sur « Plus tard » plutôt que sur « Ouvrir » : une
        // frappe réflexe ne doit pas faire quitter la page en cours.
        overlay.plusTard.focus();
    }
}
