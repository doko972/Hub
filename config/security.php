<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy en mode rapport
    |--------------------------------------------------------------------------
    |
    | À true, la politique est envoyée via Content-Security-Policy-Report-Only :
    | le navigateur signale les violations dans la console sans rien bloquer.
    |
    | Utile pour valider un changement de front sans risquer de casser une page.
    | En fonctionnement normal, laisser à false : sinon la CSP ne protège rien.
    |
    */

    'csp_report_only' => (bool) env('CSP_REPORT_ONLY', false),

];
