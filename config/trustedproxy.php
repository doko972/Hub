<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confiance
    |--------------------------------------------------------------------------
    |
    | Tant que cette valeur est nulle, les en-têtes X-Forwarded-* sont ignorés :
    | derrière un reverse proxy, request()->ip() renvoie l'IP du proxy (ce qui
    | fausse la limitation de débit) et isSecure() reste faux même en HTTPS.
    |
    | Renseigner TRUSTED_PROXIES avec l'IP du proxy, une liste séparée par des
    | virgules, ou "*" si — et seulement si — l'application n'est joignable
    | qu'à travers ce proxy. Faire confiance à "*" sur un serveur directement
    | exposé permettrait à n'importe qui d'usurper son IP via X-Forwarded-For.
    |
    | Cette valeur est relue à chaque requête par le middleware TrustProxies du
    | framework : elle reste donc correcte avec « php artisan config:cache ».
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
