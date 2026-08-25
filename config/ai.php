<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modèles par défaut
    |--------------------------------------------------------------------------
    |
    | Le modèle retenu quand le client n'en impose aucun. Doit exister dans
    | App\Http\Controllers\Api\ChatController::$models.
    |
    */

    'default_model' => env('AI_DEFAULT_MODEL', 'deepseek-chat'),

    // Modèle employé pour les tâches internes brèves (titres de conversation).
    // Un modèle économique suffit largement.
    'title_model' => env('AI_TITLE_MODEL', 'deepseek-chat'),

];
