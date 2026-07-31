<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content-Security-Policy avec nonce par requête.
 *
 * Deuxième ligne de défense derrière DOMPurify : même si du HTML hostile
 * franchissait l'assainissement, le navigateur refuserait d'exécuter le script
 * injecté, faute de nonce.
 *
 * Conséquence : plus aucun attribut onclick/onerror ni <script> sans nonce ne
 * s'exécute. Toute nouvelle balise <script> inline doit porter
 * nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}".
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        // Génère le nonce AVANT le rendu de la vue, et le transmet au helper
        // @vite pour que les balises qu'il produit le portent aussi.
        Vite::useCspNonce();

        $response = $next($request);

        $header = config('security.csp_report_only')
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($header, $this->policy());

        return $response;
    }

    /**
     * Origines du serveur de développement Vite, quand il tourne.
     *
     * En `npm run dev`, les assets et le websocket HMR viennent de
     * http://[::1]:5173 (ou équivalent) : sans ces origines, la CSP bloque
     * les feuilles de style et le rechargement à chaud. Le fichier public/hot
     * n'existe pas en production, où la liste reste donc vide.
     *
     * @return array{0: string[], 1: string[]} [origines http, origines ws]
     */
    private function viteDevOrigins(): array
    {
        if (!Vite::isRunningHot()) {
            return [[], []];
        }

        $origin = rtrim(trim((string) @file_get_contents(public_path('hot'))), '/');

        if ($origin === '' || !preg_match('#^https?://#', $origin)) {
            return [[], []];
        }

        $websocket = preg_replace('#^http#', 'ws', $origin);

        return [[$origin], [$origin, $websocket]];
    }

    private function policy(): string
    {
        $nonce = Vite::cspNonce();

        [$devAssets, $devConnect] = $this->viteDevOrigins();

        $devAssetSrc  = $devAssets  ? ' ' . implode(' ', $devAssets)  : '';
        $devConnectSrc = $devConnect ? ' ' . implode(' ', $devConnect) : '';

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",

            // Pas de 'unsafe-inline' ni 'unsafe-eval' : c'est tout l'intérêt.
            "script-src 'self' 'nonce-{$nonce}'{$devAssetSrc}",

            // 'unsafe-inline' reste nécessaire : ~220 attributs style= dans les
            // vues, plus les styles injectés par le lecteur Lottie.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com{$devAssetSrc}",
            "font-src 'self' data: https://fonts.gstatic.com",

            // Les images générées par DALL-E sont affichées depuis leur URL
            // d'origine avant d'être rapatriées par le proxy.
            "img-src 'self' data: blob: https:",

            "connect-src 'self'{$devConnectSrc}",
            "media-src 'self'",
            "worker-src 'self'",
            "manifest-src 'self'",
        ];

        return implode('; ', $directives);
    }
}
