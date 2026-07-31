<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Messagerie interne
    |--------------------------------------------------------------------------
    */

    // Rafraîchissement du fil ouvert. Court : on y attend une conversation
    // vivante, et seul l'onglet au premier plan sonde.
    'thread_poll_seconds' => (int) env('MESSAGING_THREAD_POLL', 5),

    // Rafraîchissement de la pastille et des notifications discrètes, depuis
    // n'importe quelle page du Hub. Plus espacé : c'est un bruit de fond.
    'unread_poll_seconds' => (int) env('MESSAGING_UNREAD_POLL', 20),

];
