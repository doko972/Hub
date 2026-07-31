/**
 * Entrée minimale pour les pages qui utilisent la charte Cortex sans avoir
 * besoin des librairies de rendu des conversations (marked, highlight.js).
 *
 * Même raison que dans chat.js : on passe par un module JS plutôt que par une
 * entrée .scss, sans quoi le serveur de développement Vite renvoie la feuille
 * en text/javascript et le navigateur la rejette.
 */
import '../scss/app.scss';
