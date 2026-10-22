<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Générateur de QR Code
    |--------------------------------------------------------------------------
    */

    // Serveur de fichiers prérempli dans la configuration SIP. Il est le même
    // pour tous les postes : le saisir à chaque fois serait une occasion de
    // faute de frappe. Il reste modifiable champ par champ.
    //
    // En configuration plutôt qu'en dur dans la vue, pour qu'un changement de
    // domaine se fasse par le .env, sans toucher au code.
    'file_server' => env('QR_FILE_SERVER', 'https://fichiers.hrtelecoms.fr'),

];
