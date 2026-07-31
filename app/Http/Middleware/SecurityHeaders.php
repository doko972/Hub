<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité appliqués à toutes les réponses.
 *
 * La CSP est gérée séparément (voir ContentSecurityPolicy) : elle demande un
 * nonce par requête, ce que ce middleware n'a pas à connaître.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Les réponses en flux (SSE du chat) sont envoyées au fil de l'eau ;
        // on pose quand même les en-têtes, ils partent avec le premier octet.
        $headers = $response->headers;

        // Empêche le navigateur de deviner un type MIME (ex. exécuter en JS un
        // fichier uploadé servi en image).
        $headers->set('X-Content-Type-Options', 'nosniff');

        // Anti-clickjacking. SAMEORIGIN et non DENY : le chat est intégrable
        // dans le tableau de bord via ?embedded=1.
        $headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Ne fuite pas l'URL complète (qui peut contenir un token de partage)
        // vers les sites tiers ouverts depuis l'application.
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Aucune de ces API n'est utilisée par le Hub.
        $headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), interest-cohort=()'
        );

        // Isole la fenêtre des documents tiers. « allow-popups » est nécessaire :
        // la connexion Google Calendar passe par une popup dont l'ouvreur doit
        // rester accessible pour détecter la fermeture.
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        // HSTS uniquement sur une connexion déjà sécurisée : l'envoyer en HTTP
        // n'a aucun effet, et le poser en développement rendrait localhost
        // inaccessible en clair pendant six mois.
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=15552000; includeSubDomains');
        }

        return $response;
    }
}
