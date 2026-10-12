/**
 * Assemble la page autonome du générateur de QR Code.
 *
 *   node livraison/qr-code/assembler.cjs
 *
 * Le gabarit (modele.html) porte tout le balisage, le style et la logique ;
 * ce script y injecte la bibliothèque d'encodage pour produire un fichier
 * unique, remettable tel quel à un client.
 *
 * La bibliothèque retenue est « qrcode-generator » (MIT) et non QRious (GPL-3.0)
 * qui équipe le Hub : la GPL impose à tout fichier l'embarquant d'être
 * lui-même sous GPL, avec mise à disposition des sources — une contrainte que
 * l'on ne veut pas transmettre au client. Elle est volontairement injectée non
 * minifiée : l'en-tête de licence reste lisible dans le livrable.
 */

const fs   = require('fs');
const path = require('path');

const racine   = path.resolve(__dirname, '..', '..');
const gabarit  = path.join(__dirname, 'modele.html');
const librairie = path.join(racine, 'node_modules', 'qrcode-generator', 'dist', 'qrcode.js');
const sortie   = path.join(__dirname, 'generateur-qr-code.html');

const MARQUEUR = '/* <<<BIBLIOTHEQUE_QR>>> */';

for (const fichier of [gabarit, librairie]) {
    if (!fs.existsSync(fichier)) {
        console.error(`Fichier introuvable : ${fichier}`);
        if (fichier === librairie) {
            console.error('Lancez « npm install » pour récupérer qrcode-generator.');
        }
        process.exit(1);
    }
}

const modele = fs.readFileSync(gabarit, 'utf8');

if (!modele.includes(MARQUEUR)) {
    console.error(`Le marqueur ${MARQUEUR} est absent du gabarit : rien n'a été écrit.`);
    process.exit(1);
}

let source = fs.readFileSync(librairie, 'utf8');

// La bibliothèque est un module UMD : dans une balise <script> elle s'exporterait
// dans « module.exports » si un bundler l'avait définie. Ici rien de tel, elle
// s'expose donc sur window — mais on vérifie, plutôt que de le supposer.
if (!source.includes('qrcode')) {
    console.error("Le fichier de la bibliothèque ne ressemble pas à qrcode-generator.");
    process.exit(1);
}

// Une balise </script> à l'intérieur du script fermerait le bloc par surprise.
// Le cas ne se présente pas aujourd'hui, mais la garde coûte une ligne.
source = source.replace(/<\/script>/gi, '<\\/script>');

// Fonction de remplacement, et non chaîne : dans une chaîne, « $' », « $& » et
// « $` » sont des motifs interprétés par replace(). La bibliothèque contient
// « case '$' : return 37; » — le « $' » y avalait le guillemet et réinjectait
// la fin du gabarit, produisant un fichier au JavaScript invalide.
const resultat = modele.replace(MARQUEUR, () => source);

fs.writeFileSync(sortie, resultat, 'utf8');

const taille = (Buffer.byteLength(resultat, 'utf8') / 1024).toFixed(0);
console.log(`✓ ${path.relative(racine, sortie)} — ${taille} Ko, autonome.`);
