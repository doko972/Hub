<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Recherche de GIF via Tenor.
 *
 * La clé reste côté serveur : le navigateur ne parle jamais à Tenor, ce qui
 * évite d'exposer la clé et de laisser fuiter les recherches des utilisateurs
 * vers un tiers. Le GIF choisi est ensuite rapatrié et stocké comme pièce
 * jointe ordinaire — même principe que le proxy DALL-E existant.
 */
class TenorGifs
{
    private const ENDPOINT = 'https://tenor.googleapis.com/v2/search';

    /**
     * Hôtes depuis lesquels un GIF peut être rapatrié.
     *
     * Sans cette liste, un client malveillant pourrait faire télécharger au
     * serveur n'importe quelle URL interne (SSRF).
     */
    public const ALLOWED_HOSTS = [
        'media.tenor.com',
        'media1.tenor.com',
        'media2.tenor.com',
        'c.tenor.com',
    ];

    /**
     * Taille maximale d'un GIF rapatrié, en octets.
     */
    public const MAX_BYTES = 8 * 1024 * 1024;

    public static function isConfigured(): bool
    {
        return filled(config('services.tenor.api_key'));
    }

    public static function isAllowedUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $sche = parse_url($url, PHP_URL_SCHEME);

        return $sche === 'https' && in_array($host, self::ALLOWED_HOSTS, true);
    }

    /**
     * @return array<int, array{id: string, description: string, preview: string, url: string}>
     */
    public function search(string $query, int $limit = 24): array
    {
        if (!self::isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(8)->get(self::ENDPOINT, [
                'q'            => $query,
                'key'          => config('services.tenor.api_key'),
                'client_key'   => config('services.tenor.client_key'),
                'limit'        => $limit,
                'locale'       => 'fr_FR',
                'country'      => 'FR',
                // Filtre de Tenor : on ne propose que du contenu tout public.
                'contentfilter' => 'high',
                // tinygif pour la grille, gif pour l'envoi.
                'media_filter' => 'tinygif,gif',
            ]);

            if (!$response->successful()) {
                Log::warning('Recherche Tenor en échec', ['status' => $response->status()]);

                return [];
            }

            return collect($response->json('results', []))
                ->map(function (array $result) {
                    $formats = $result['media_formats'] ?? [];

                    $preview = $formats['tinygif']['url'] ?? null;
                    $complet = $formats['gif']['url'] ?? $preview;

                    if (!$preview || !$complet || !self::isAllowedUrl($complet)) {
                        return null;
                    }

                    return [
                        'id'          => (string) ($result['id'] ?? ''),
                        'description' => mb_substr($result['content_description'] ?? 'GIF', 0, 120),
                        'preview'     => $preview,
                        'url'         => $complet,
                    ];
                })
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Recherche Tenor impossible', ['exception' => $e]);

            return [];
        }
    }
}
