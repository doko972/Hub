/**
 * Signal de vie.
 *
 * La présence repose sur la date de dernière requête (middleware TrackLastSeen).
 * Or toutes les pages n'en génèrent pas : le chat IA, par exemple, n'a ni
 * badge de non-lus ni panneau de présence, donc aucun sondage — ses
 * utilisateurs passaient hors ligne au bout de quelques minutes alors qu'ils
 * étaient en train de s'en servir.
 *
 * Ce module est chargé par app.js, donc présent sur toutes les pages : un
 * appel très léger suffit à maintenir la présence à jour.
 */

const INTERVAL = 120000; // 2 minutes

export function initHeartbeat() {
    const endpoint = document.querySelector('meta[name="presence-ping"]')?.content;
    if (!endpoint) return;

    async function ping() {
        try {
            // Volontairement émis même onglet masqué : un Hub ouvert en
            // arrière-plan pendant qu'on travaille dans un outil reste une
            // présence réelle.
            await fetch(endpoint, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                // La réponse est vide : rien à mettre en cache.
                cache: 'no-store',
            });
        } catch {
            // Coupure réseau : le prochain battement retentera.
        }
    }

    setInterval(ping, INTERVAL);

    // Au retour sur l'onglet, on se signale sans attendre le prochain cycle.
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) ping();
    });
}
