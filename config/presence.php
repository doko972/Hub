<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Présence des utilisateurs
    |--------------------------------------------------------------------------
    */

    // Au-delà de ce délai sans activité, l'utilisateur est considéré hors ligne.
    // Doit rester supérieur à l'intervalle d'écriture ci-dessous, sinon un
    // utilisateur actif clignoterait entre les deux états.
    'online_within_minutes' => (int) env('PRESENCE_ONLINE_MINUTES', 5),

    // Fréquence maximale d'écriture de last_seen_at. Sans ce garde-fou, chaque
    // requête déclencherait un UPDATE.
    'write_every_minutes' => (int) env('PRESENCE_WRITE_MINUTES', 1),

    // Les utilisateurs hors ligne restent listés (grisés) pendant cette durée ;
    // au-delà, ils disparaissent du panneau.
    'recent_within_hours' => (int) env('PRESENCE_RECENT_HOURS', 24),

    // Intervalle de rafraîchissement côté navigateur, en secondes.
    'poll_seconds' => (int) env('PRESENCE_POLL_SECONDS', 30),

    // Garde-fou d'affichage : nombre maximum de personnes listées.
    'max_users' => (int) env('PRESENCE_MAX_USERS', 20),

];
