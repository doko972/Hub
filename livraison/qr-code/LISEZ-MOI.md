# Générateur de QR Code — page autonome

Version hors ligne de l'outil `/tools/qr-code` du Hub, destinée à être remise
à un client.

## Le fichier à livrer

`generateur-qr-code.html` — **c'est le seul fichier à transmettre.**

Il s'ouvre par double-clic dans n'importe quel navigateur récent : pas de
serveur, pas d'installation, pas de connexion internet. Rien de ce que
l'utilisateur saisit ne sort de son navigateur.

## Ce qui change par rapport à la version du Hub

| | Hub | Page autonome |
|---|---|---|
| Configurations enregistrées | sur le serveur, chiffrées | dans un fichier `.json` que l'utilisateur range où il veut |
| Bibliothèque d'encodage | QRious (GPL-3.0) | qrcode-generator (MIT) |

**Les configurations.** Sans serveur, il n'y a nulle part où chiffrer les mots
de passe SIP et administrateur. Les déposer dans le stockage du navigateur les
laisserait en clair sur le poste, à l'insu de l'utilisateur. On passe donc par
un fichier : il est visible, se range où l'on veut et se supprime d'un geste.

**La licence.** QRious est sous GPL-3.0 : tout fichier l'embarquant doit être
lui-même placé sous GPL-3.0, avec mise à disposition des sources. L'usage
interne du Hub n'est pas concerné, mais remettre le fichier à un client est une
diffusion. La page autonome utilise donc une bibliothèque MIT, qui n'impose
rien au destinataire.

## Régénérer le fichier

```bash
node livraison/qr-code/assembler.cjs    # modele.html + bibliothèque → livrable
node livraison/qr-code/verifier.cjs     # exécute la page et relit les QR produits
```

Modifier `modele.html`, jamais `generateur-qr-code.html` : ce dernier est
écrasé à chaque assemblage.

`verifier.cjs` ne se contente pas de vérifier que la page s'affiche : il
redécode les QR produits avec un décodeur indépendant. Un QR illisible ne se
voit pas à l'œil nu, et celui-ci part chez un client.

Les deux scripts ont besoin de `npm install` (dépendances de développement
`qrcode-generator`, `jsdom` et `jsqr`).
