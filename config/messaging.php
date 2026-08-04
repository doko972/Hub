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

        // Liste blanche : tout ce qui n'y figure pas est refusé. Volontairement
        // sans svg, html, ni exécutables — un fichier déposé ici est
        // téléchargeable par tous les participants du fil.
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
            'pdf', 'txt', 'csv', 'md', 'rtf',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'odt', 'ods', 'odp',
            'zip',
        ],

        // Seuls ces types sont affichés dans le fil ; le reste est proposé au
        // téléchargement, avec Content-Disposition: attachment.
        'inline_mimes' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
        ],
    ],

];
