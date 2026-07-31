/**
 * Signal sonore à la réception d'un message.
 *
 * Le son est synthétisé par l'API Web Audio plutôt que chargé depuis un
 * fichier : aucun binaire à versionner, aucune requête réseau, et rien à
 * autoriser dans la CSP.
 */

const STORAGE_KEY = 'hub.sound.enabled';

let context = null;

export function soundEnabled() {
    // Actif par défaut : une notification inaudible passe inaperçue, ce qui
    // était précisément le reproche fait à la première version.
    return localStorage.getItem(STORAGE_KEY) !== '0';
}

export function setSoundEnabled(enabled) {
    try {
        localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
    } catch {
        // Stockage indisponible : le réglage ne survivra pas à la session.
    }
}

/**
 * Les navigateurs refusent de produire du son tant que l'utilisateur n'a pas
 * interagi avec la page. On crée donc le contexte au premier geste, et on le
 * réveille s'il a été suspendu.
 */
function audioContext() {
    const Constructeur = window.AudioContext || window.webkitAudioContext;
    if (!Constructeur) return null;

    context ??= new Constructeur();

    if (context.state === 'suspended') {
        context.resume().catch(() => {});
    }

    return context;
}

document.addEventListener('pointerdown', () => audioContext(), { once: true });
document.addEventListener('keydown', () => audioContext(), { once: true });

/**
 * Deux notes brèves, montantes : assez présent pour être remarqué dans un
 * bureau, assez court pour ne pas être pénible vingt fois par jour.
 */
export function playNotificationSound() {
    if (!soundEnabled()) return;

    const ctx = audioContext();
    if (!ctx || ctx.state !== 'running') return;

    const maintenant = ctx.currentTime;

    [
        { frequence: 880, debut: 0,    duree: 0.12 },
        { frequence: 1170, debut: 0.11, duree: 0.16 },
    ].forEach(({ frequence, debut, duree }) => {
        const oscillateur = ctx.createOscillator();
        const gain        = ctx.createGain();

        oscillateur.type = 'sine';
        oscillateur.frequency.value = frequence;

        // Enveloppe douce : une onde coupée net produit un clic désagréable.
        gain.gain.setValueAtTime(0.0001, maintenant + debut);
        gain.gain.exponentialRampToValueAtTime(0.18, maintenant + debut + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, maintenant + debut + duree);

        oscillateur.connect(gain).connect(ctx.destination);
        oscillateur.start(maintenant + debut);
        oscillateur.stop(maintenant + debut + duree + 0.02);
    });
}
