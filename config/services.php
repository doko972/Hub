<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Intégration Chatbot-api (bulle de chat dans le dashboard)
    'chatbot' => [
        'url'    => env('CHATBOT_URL'),
        'secret' => env('CHATBOT_SECRET'),
    ],
        'brave' => [
        'api_key' => env('BRAVE_SEARCH_API_KEY'),
    ],
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],
    'tavily' => [
        'api_key' => env('TAVILY_API_KEY'),
    ],
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    // (La clé OpenAI est gérée par config/openai.php, publié par openai-php/laravel.)

    'removebg' => [
        'api_key' => env('REMOVEBG_API_KEY'),
    ],

    // Notifications push navigateur (VAPID)
    'webpush' => [
        'subject'     => env('VAPID_SUBJECT', env('APP_URL')),
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    // Adresse mise en copie des emails de rappel / résumé
    'contact' => [
        'email' => env('CONTACT_EMAIL'),
    ],

    // Modèle de langage compatible avec l'API OpenAI, mais bien moins cher.
    // Le protocole étant identique, le même client sert les deux : seules
    // l'URL de base et la clé changent.
    'deepseek' => [
        'api_key'  => env('DEEPSEEK_API_KEY'),
        'base_uri' => env('DEEPSEEK_BASE_URI', 'api.deepseek.com/v1'),
    ],
    // Recherche de GIF dans la messagerie (https://developers.giphy.com).
    // Sans clé, l'onglet GIF n'apparaît pas : le reste fonctionne normalement.
    //
    // Giphy plutôt que Tenor : ce dernier a cessé d'accepter de nouveaux
    // clients en janvier 2026 avant d'être arrêté.
    'giphy' => [
        'api_key' => env('GIPHY_API_KEY'),
    ],

    // Inventaire des machines OVHcloud, utilisé pour importer les centrex sans
    // ressaisir les adresses IP (voir App\Services\OvhInventory).
    //
    // Le jeton se crée sur https://api.ovh.com/createToken/ en lecture seule :
    // GET /cloud/project et GET /cloud/project/* pour les instances Public
    // Cloud, GET /vps et GET /vps/* pour les VPS Bare Metal.
    //
    // OVH_CLOUD_PROJECTS restreint l'import à certains projets Public Cloud
    // (ids séparés par des virgules, celui qui figure dans l'URL du manager).
    // Vide : tous les projets du compte.
    'ovh' => [
        'endpoint'           => env('OVH_ENDPOINT', 'ovh-eu'),
        'application_key'    => env('OVH_APPLICATION_KEY'),
        'application_secret' => env('OVH_APPLICATION_SECRET'),
        'consumer_key'       => env('OVH_CONSUMER_KEY'),
        'cloud_projects'     => env('OVH_CLOUD_PROJECTS', ''),
    ],

    // Identifiants d'administration posés sur chaque centrex créé par l'import
    // OVH : les machines sortent toutes de la même image, avec le même couple
    // par défaut. Ils sont copiés dans la fiche, pas lus à la volée — le mot de
    // passe d'une machine finit toujours par diverger, et la fiche doit alors
    // pouvoir suivre sans toucher à la configuration du serveur.
    //
    // Le mot de passe est chiffré au repos dans la fiche (cast 'encrypted').
    // Ici, il est en clair dans le .env, comme les autres secrets du projet :
    // ce fichier n'est pas versionné et ne doit pas le devenir.
    'centrex' => [
        'default_login'    => env('CENTREX_DEFAULT_LOGIN'),
        'default_password' => env('CENTREX_DEFAULT_PASSWORD'),
    ],
];
