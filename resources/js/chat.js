/**
 * chat.js — librairies de rendu des conversations.
 *
 * Point d'entrée séparé d'app.js : seules les pages de chat et de partage en
 * ont besoin, inutile d'alourdir le reste du site.
 *
 * Ces librairies étaient auparavant chargées depuis trois CDN tiers sans
 * contrôle d'intégrité (jsdelivr, cdnjs, unpkg) : une compromission de l'un
 * d'eux revenait à une exécution de code arbitraire sur des pages
 * authentifiées. Elles sont désormais versionnées dans package.json et
 * servies depuis notre propre origine.
 */

// Feuille de styles de l'interface Cortex.
//
// Importée ici plutôt que déclarée en entrée @vite : en `npm run dev`, une
// entrée .scss produit un <link rel="stylesheet"> vers le serveur Vite, qui la
// sert en text/javascript — le navigateur refuse alors de l'appliquer et la
// page s'affiche sans style. Passée par le module, elle est injectée par Vite
// en dev et extraite normalement au build.
import '../scss/app.scss';

import { marked } from 'marked';
import hljs from 'highlight.js/lib/common';
import 'highlight.js/styles/atom-one-dark.css';
import '@lottiefiles/lottie-player';

// Configuration commune aux deux vues (auparavant dupliquée dans chaque
// template). Les modules Vite s'exécutent avant DOMContentLoaded : marked est
// donc prêt avant tout appel à formatMarkdown().
marked.setOptions({
    highlight(code, lang) {
        if (lang && hljs.getLanguage(lang)) {
            return hljs.highlight(code, { language: lang }).value;
        }
        return hljs.highlightAuto(code).value;
    },
    breaks: true,
    gfm: true,
});

window.marked = marked;
window.hljs = hljs;
