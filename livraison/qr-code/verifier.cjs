/**
 * Vérifie la page autonome du générateur de QR Code.
 *
 *   node livraison/qr-code/verifier.cjs
 *
 * Deux niveaux :
 *   1. la page s'exécute réellement dans un DOM simulé — onglets, saisie,
 *      aperçu, enregistrement et chargement d'une configuration ;
 *   2. les QR produits sont relus par un décodeur indépendant, pour que
 *      « ça s'affiche » ne soit pas confondu avec « c'est scannable ».
 *
 * Le second point est le plus important : la page est remise à un client, et
 * un QR illisible ne se voit pas à l'œil nu.
 */

const fs   = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const racine = path.resolve(__dirname, '..', '..');
const page   = path.join(__dirname, 'generateur-qr-code.html');
const jsQR   = require(path.join(racine, 'node_modules', 'jsqr', 'dist', 'jsQR.js'));

let echecs = 0;

function verifier(nom, condition, detail) {
    if (condition) {
        console.log(`✓ ${nom}`);
    } else {
        echecs++;
        console.log(`✗ ${nom}${detail ? `\n    ${detail}` : ''}`);
    }
}

// ---------------------------------------------------------------------------
// Toile simulée : jsdom ne dessine pas. On enregistre les rectangles peints,
// ce qui permet de reconstituer l'image et de la soumettre au décodeur.
// ---------------------------------------------------------------------------
const etatToile = { taille: 0, rects: [] };

function installerToile(fenetre) {
    const etat = etatToile;

    fenetre.HTMLCanvasElement.prototype.getContext = function () {
        const toile = this;
        return {
            set fillStyle(v) { this._fill = v; },
            get fillStyle()  { return this._fill; },
            fillRect(x, y, w, h) {
                etat.taille = toile.width;
                etat.rects.push({ x, y, w, h, sombre: this._fill === '#000000' });
            },
        };
    };

    fenetre.HTMLCanvasElement.prototype.toDataURL = () => 'data:image/png;base64,SIMULE';
}

/** Reconstitue les pixels à partir des rectangles peints, puis décode. */
function decoder(etat) {
    const n = etat.taille;
    if (!n) return null;

    const pixels = new Uint8ClampedArray(n * n * 4).fill(255);

    for (const r of etat.rects) {
        if (!r.sombre) continue;
        for (let y = r.y; y < r.y + r.h; y++) {
            for (let x = r.x; x < r.x + r.w; x++) {
                const i = (y * n + x) * 4;
                pixels[i] = pixels[i + 1] = pixels[i + 2] = 0;
            }
        }
    }

    return jsQR(pixels, n, n)?.data ?? null;
}

// ---------------------------------------------------------------------------
// Chargement de la page
// ---------------------------------------------------------------------------
const dom = new JSDOM(fs.readFileSync(page, 'utf8'), {
    runScripts: 'dangerously',
    beforeParse: installerToile,
});

const { window } = dom;
const doc = window.document;

const $ = (sel) => doc.querySelector(sel);

function saisir(id, valeur) {
    const el = doc.getElementById(id);
    el.value = valeur;
    el.dispatchEvent(new window.Event('input', { bubbles: true }));
}

function apercu() {
    return doc.getElementById('qr-data-box').textContent;
}

function qrAffiche() {
    return doc.getElementById('qr-canvas').style.display === 'block';
}

// ---- 1. État initial ------------------------------------------------------
verifier(
    'la bibliothèque d\'encodage est disponible',
    typeof window.qrcode === 'function'
);

verifier(
    'au démarrage, l\'aperçu invite à remplir le formulaire',
    doc.getElementById('qr-placeholder').style.display !== 'none'
    && doc.getElementById('qr-actions').style.display === 'none'
);

const SERVEUR_FICHIERS = 'https://fichiers.hrtelecoms.fr';

verifier(
    'le serveur de fichiers est prérempli',
    doc.getElementById('sip-file-server').value === SERVEUR_FICHIERS,
    doc.getElementById('sip-file-server').value
);

verifier(
    'les fonctions vidéo et messagerie sont actives par défaut',
    doc.getElementById('sip-video').checked && doc.getElementById('sip-messaging').checked
);

verifier(
    'la clé du serveur de fichiers est masquée',
    doc.getElementById('sip-file-key').type === 'password'
);

// ---- 2. Configuration SIP -------------------------------------------------
saisir('sip-username', '1002');
saisir('sip-password', 'M0tDeP4sse!');
saisir('sip-domain', '51.91.145.39');
saisir('sip-display', 'Benoît Lefèvre');
saisir('sip-port', '5060');
saisir('sip-admin-password', 'Admin2024');
saisir('sip-logo', 'https://hrtelecoms.fr/marques/clientA.png');
saisir('sip-file-key', 'LA_CLE');

const sip = JSON.parse(apercu());

verifier(
    'la structure du JSON est celle attendue par les postes',
    JSON.stringify(Object.keys(sip)) === JSON.stringify([
        'username', 'password', 'domain', 'display', 'transport', 'port',
        'admin_password', 'logo', 'file_server', 'file_key', 'video', 'messaging',
    ]),
    Object.keys(sip).join(', ')
);

verifier(
    'logo, serveur de fichiers et clé sont repris tels quels',
    sip.logo === 'https://hrtelecoms.fr/marques/clientA.png'
    && sip.file_server === SERVEUR_FICHIERS
    && sip.file_key === 'LA_CLE',
    JSON.stringify(sip)
);

verifier(
    'les deux fonctions sont des booléens',
    sip.video === true && sip.messaging === true
);

verifier(
    'la configuration SIP reprend fidèlement la saisie',
    sip.username === '1002' && sip.password === 'M0tDeP4sse!'
    && sip.domain === '51.91.145.39' && sip.display === 'Benoît Lefèvre'
    && sip.transport === 'udp' && sip.port === 5060
    && sip.admin_password === 'Admin2024',
    JSON.stringify(sip)
);

verifier('le port est un nombre, pas une chaîne', typeof sip.port === 'number');

verifier('le QR code est affiché dès que le formulaire est rempli', qrAffiche());

etatToile.rects = [];
saisir('sip-username', '1002');
verifier(
    'le QR de la configuration SIP est relu à l\'identique par un décodeur',
    decoder(etatToile) === JSON.stringify(sip),
    `relu : ${String(decoder(etatToile)).slice(0, 80)}`
);

// ---- 3. Enregistrement / chargement d'une configuration -------------------
const telecharges = [];
window.URL.createObjectURL = (blob) => {
    telecharges.push(blob);
    return 'blob:simule';
};
window.URL.revokeObjectURL = () => {};
window.HTMLAnchorElement.prototype.click = function () {};

window.prompt = () => 'Poste accueil';
$('#cfg-save').click();

verifier('l\'enregistrement produit bien un fichier', telecharges.length === 1);

// Le contenu du Blob est relu via l'API de jsdom.
telecharges[0].text().then((contenu) => {
    const etat = JSON.parse(contenu);

    verifier(
        'le fichier enregistré contient l\'intégralité du formulaire',
        etat.version === 1
        && etat.sip['sip-username'] === '1002'
        && etat.sip['sip-admin-password'] === 'Admin2024'
        && etat.sip['sip-file-key'] === 'LA_CLE'
        && Array.isArray(etat.contacts),
        JSON.stringify(etat).slice(0, 120)
    );

    verifier(
        'les cases sont enregistrées par leur état, pas par la chaîne « on »',
        etat.sip['sip-video'] === true && etat.sip['sip-messaging'] === true,
        JSON.stringify({ video: etat.sip['sip-video'], messaging: etat.sip['sip-messaging'] })
    );

    // ---- 4. Onglet « QR Code libre », sous-onglet Contact -----------------
    doc.querySelector('.qr-tab[data-tab="qr"]').click();
    doc.querySelector('.qr-subtab[data-subtab="contact"]').click();

    verifier(
        'le changement d\'onglet affiche le bon panneau',
        doc.getElementById('qr-panel-qr').classList.contains('is-active')
        && !doc.getElementById('qr-panel-sip').classList.contains('is-active')
    );

    saisir('qr-c-first', 'Benoît');
    saisir('qr-c-last', 'Lefèvre');
    saisir('qr-c-phone', '+33 6 12 34 56 78');
    saisir('qr-c-email', 'benoit@exemple.fr');

    const vcard = apercu();

    verifier(
        'la vCard est correctement composée',
        vcard.startsWith('BEGIN:VCARD') && vcard.includes('FN:Benoît Lefèvre')
        && vcard.endsWith('END:VCARD')
    );

    etatToile.rects = [];
    saisir('qr-c-email', 'benoit@exemple.fr');

    verifier(
        'les accents de la vCard survivent à l\'encodage',
        decoder(etatToile) === vcard,
        `relu : ${String(decoder(etatToile)).slice(0, 80)}`
    );

    // ---- 5. URL : protocole ajouté ---------------------------------------
    doc.querySelector('.qr-subtab[data-subtab="url"]').click();
    saisir('qr-url', 'exemple.fr');

    verifier('le protocole https:// est ajouté si absent', apercu() === 'https://exemple.fr');

    // ---- 6. Garde-fou sur les données volumineuses -----------------------
    doc.querySelector('.qr-subtab[data-subtab="text"]').click();
    saisir('qr-text', 'A'.repeat(2000));

    verifier(
        'au-delà de 1800 octets, l\'outil avertit et masque le QR code',
        doc.getElementById('qr-warning').style.display === 'block'
        && !qrAffiche()
        && doc.getElementById('qr-btn-qr').disabled === true
    );

    saisir('qr-text', 'Réunion à 14h');

    verifier(
        'et le QR revient dès que les données repassent sous la limite',
        qrAffiche() && doc.getElementById('qr-btn-qr').disabled === false
    );

    // ---- 6 bis. Onglet Annuaire ------------------------------------------
    const ANNUAIRE = 'https://centrex.hrtelecoms.fr/annuaire/J8orL237CBd4kZzadH7rJNUt1QtRzBheevXpIukE';
    const AUTRE    = 'https://centrex.hrtelecoms.fr/annuaire/K9prM348DCe5lAabI8sKOVu2RuSAiCfffWqYjvlF';

    doc.querySelector('.qr-tab[data-tab="annuaire"]').click();

    verifier(
        'l\'onglet Annuaire s\'ouvre sans annuaire supplémentaire',
        doc.getElementById('qr-panel-annuaire').classList.contains('is-active')
        && doc.querySelectorAll('.qr-directory-row').length === 0
    );

    saisir('dir-url', ANNUAIRE);

    verifier(
        'l\'annuaire principal seul ne produit pas « directory_extra »',
        !('directory_extra' in JSON.parse(apercu())),
        apercu()
    );

    doc.getElementById('qr-add-directory').click();
    const ligne = doc.querySelector('.qr-directory-row input');
    ligne.value = AUTRE;
    ligne.dispatchEvent(new window.Event('input', { bubbles: true }));

    const annuaire = JSON.parse(apercu());

    verifier(
        'le JSON d\'annuaire a la forme attendue',
        annuaire.directory_url === ANNUAIRE
        && Array.isArray(annuaire.directory_extra)
        && annuaire.directory_extra.length === 1
        && annuaire.directory_extra[0] === AUTRE,
        apercu()
    );

    // Ces URL sont longues : mieux vaut confirmer que le QR reste lisible.
    etatToile.rects = [];
    ligne.dispatchEvent(new window.Event('input', { bubbles: true }));

    verifier(
        'le QR d\'annuaire est relu à l\'identique par un décodeur',
        decoder(etatToile) === JSON.stringify(annuaire),
        `relu : ${String(decoder(etatToile)).slice(0, 80)}`
    );

    doc.getElementById('qr-clear-annuaire').click();

    verifier(
        '« Vider les champs » efface l\'annuaire et ses lignes',
        doc.getElementById('dir-url').value === ''
        && doc.querySelectorAll('.qr-directory-row').length === 0
    );

    // ---- 7. Chargement d'une configuration -------------------------------
    doc.querySelector('.qr-tab[data-tab="sip"]').click();

    // Décocher doit écrire false, et non retirer la clé : sans cela, le poste
    // appliquerait son propre réglage et décocher n'aurait aucun effet.
    doc.getElementById('sip-video').checked = false;
    doc.getElementById('sip-video').dispatchEvent(new window.Event('change', { bubbles: true }));

    const decoche = JSON.parse(apercu());

    verifier(
        'décocher une fonction écrit false plutôt que de retirer la clé',
        'video' in decoche && decoche.video === false && decoche.messaging === true,
        apercu()
    );

    doc.getElementById('qr-clear-sip').click();

    verifier(
        'le bouton « Vider » remet bien les champs à zéro',
        doc.getElementById('sip-username').value === ''
        && doc.getElementById('sip-admin-password').value === ''
        && doc.getElementById('sip-file-key').value === ''
    );

    verifier(
        '« Vider » restaure le préremplissage, au lieu de tout effacer',
        doc.getElementById('sip-file-server').value === SERVEUR_FICHIERS
        && doc.getElementById('sip-video').checked
        && doc.getElementById('sip-messaging').checked
        && doc.getElementById('sip-transport').value === 'udp'
    );

    // On rejoue le fichier enregistré plus haut, comme le ferait un chargement.
    const champ = doc.getElementById('cfg-file');
    Object.defineProperty(champ, 'files', { value: [new window.File([contenu], 'poste.json')] });
    Object.defineProperty(champ, 'value', { value: 'poste.json', writable: true });
    champ.dispatchEvent(new window.Event('change'));

    setTimeout(() => {
        verifier(
            'le chargement d\'un fichier restaure la configuration complète',
            doc.getElementById('sip-username').value === '1002'
            && doc.getElementById('sip-admin-password').value === 'Admin2024'
            && doc.getElementById('sip-display').value === 'Benoît Lefèvre'
        );

        console.log(echecs === 0
            ? '\nLa page autonome se comporte comme attendu.'
            : `\n${echecs} vérification(s) en échec.`);

        process.exit(echecs === 0 ? 0 : 1);
    }, 50);
});
