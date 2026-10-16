<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Messagerie interne
    |--------------------------------------------------------------------------
    */

    // Rafraîchissement du fil ouvert. Court : on y attend une conversation
    // vivante, et seul l'onglet au premier plan sonde.
    // Descendu à 3 s : ce sondage porte aussi l'indicateur de frappe, qui perd
    // tout intérêt s'il arrive trop tard. Seul l'onglet au premier plan sonde.
    'thread_poll_seconds' => (int) env('MESSAGING_THREAD_POLL', 3),

    // Durée de validité d'un signal de frappe. Doit rester supérieure à
    // l'intervalle de renouvellement côté client (3 s), sinon l'indicateur
    // clignote entre deux signaux.
    'typing_ttl_seconds' => (int) env('MESSAGING_TYPING_TTL', 8),

    // Rafraîchissement de la pastille et des notifications discrètes, depuis
    // n'importe quelle page du Hub. Plus espacé : c'est un bruit de fond.
    'unread_poll_seconds' => (int) env('MESSAGING_UNREAD_POLL', 20),

    /*
    |--------------------------------------------------------------------------
    | Pièces jointes
    |--------------------------------------------------------------------------
    */

    'attachments' => [

        'max_files'   => (int) env('MESSAGING_MAX_FILES', 5),

        // En kilo-octets. Doit rester sous upload_max_filesize et post_max_size
        // de PHP, sinon le serveur rejette la requête avant Laravel.
        'max_size_kb' => (int) env('MESSAGING_MAX_SIZE_KB', 10240),

        // Liste de REFUS, et non liste blanche : une liste blanche obligeait à
        // la compléter à chaque nouveau besoin, et refusait sans raison des
        // fichiers techniques courants (.conf, .xml, .json…).
        //
        // L'ouverture est sans danger pour le Hub : les pièces jointes vivent
        // hors du dossier public, ne sont servies que par une route
        // authentifiée, toujours avec Content-Disposition: attachment (sauf
        // les images listées plus bas) et sous X-Content-Type-Options: nosniff.
        // Un .html ou un .php déposé ici est téléchargé, jamais exécuté.
        //
        // Ce qui reste refusé, c'est ce qu'un double-clic lance sous Windows :
        // la messagerie ne doit pas pouvoir servir à diffuser un programme à
        // toute l'entreprise depuis un compte de confiance.
        'blocked_extensions' => [
            'exe', 'com', 'scr', 'pif', 'msi', 'msp', 'app', 'gadget',
            'bat', 'cmd', 'hta', 'vbs', 'vbe', 'wsf', 'wsh',
            'ps1', 'psm1', 'ps1xml',
            'cpl', 'msc', 'inf', 'reg', 'lnk', 'jar', 'dll', 'sys',
        ],

        // Un fichier annoncé comme image doit réellement en être une : c'est ce
        // qui empêche de faire passer un script pour une photo.
        'image_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],

        // Seuls ces types sont affichés dans le fil ; le reste est proposé au
        // téléchargement, avec Content-Disposition: attachment.
        'inline_mimes' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
        ],
    ],

];
