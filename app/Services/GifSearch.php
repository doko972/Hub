<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Recherche de GIF via Giphy.
 *
 * Le fournisseur est isolé dans cette classe : Tenor, envisagé au départ, a
 * cessé d'accepter de nouveaux clients en janvier 2026 avant d'être arrêté.
 * Changer de nouveau de service ne devrait toucher que ce fichier.
 *
 * La clé reste côté serveur : le navigateur ne parle jamais à Giphy, ce qui
 * évite de l'exposer et de laisser fuiter les recherches des utilisateurs vers
 * un tiers. Le GIF choisi est ensuite rapatrié et stocké comme pièce jointe
 * ordinaire — même principe que le proxy DALL-E existant.
 */
class GifSearch
{
    private const ENDPOINT = 'https://api.giphy.com/v1/gifs/search';

    /**
     * Hôtes depuis lesquels un GIF peut être rapatrié.
     *
     * Sans cette liste, la route d'envoi ferait télécharger au serveur
     * n'importe quelle URL fournie par le client (SSRF).
     */
    public const ALLOWED_HOSTS = [
        'media.giphy.com',
        'media0.giphy.com',
        'media1.giphy.com',
        'media2.giphy.com',
        'media3.giphy.com',
        'media4.giphy.com',
        'i.giphy.com',
    ];

    /**
     * Taille maximale d'un GIF rapatrié, en octets.
     */
    public const MAX_BYTES = 8 * 1024 * 1024;

    /**
     * Rendus possibles, du plus léger au plus lourd. Leur présence n'est pas
     * garantie pour chaque GIF : on prend le premier disponible.
     */
    private const PREVIEW_RENDITIONS = ['fixed_width_small', 'fixed_height_small', 'preview_gif', 'downsized'];
    private const FULL_RENDITIONS    = ['downsized', 'downsized_medium', 'fixed_width', 'original'];

    public static function isConfigured(): bool
    {
        return filled(config('services.giphy.api_key'));
    }

    public static function isAllowedUrl(string $url): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && in_array(parse_url($url, PHP_URL_HOST), self::ALLOWED_HOSTS, true);
    }

    /**
     * @return array<int, array{id: string, description: string, preview: string, url: string}>
     */
    public function search(string $query, int $limit = 24): array
    {
        if (!self::isConfigured()) {
            return [];
        }

        // Une clé Giphy gratuite est plafonnée à 100 appels par heure :
        // mutualiser les recherches identiques évite de l'épuiser à plusieurs.
        $cle = 'gifs:' . md5(mb_strtolower(trim($query))) . ":{$limit}";

        return Cache::remember($cle, now()->addMinutes(15), fn () => $this->fetch($query, $limit));
    }

    private function fetch(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(8)->get(self::ENDPOINT, [
                'api_key' => config('services.giphy.api_key'),
                'q'       => $query,
                'limit'   => $limit,
                // « g » : tout public, la messagerie servant un cadre de travail.
                'rating'  => 'g',
                'lang'    => 'fr',
            ]);

            if (!$response->successful()) {
                Log::warning('Recherche Giphy en échec', ['status' => $response->status()]);

                return [];
            }

            return collect($response->json('data', []))
                ->map(function (array $gif) {
                    $images  = $gif['images'] ?? [];
                    $preview = $this->pickRendition($images, self::PREVIEW_RENDITIONS);
                    $complet = $this->pickRendition($images, self::FULL_RENDITIONS);

                    if (!$preview || !$complet || !self::isAllowedUrl($complet)) {
                        return null;
                    }

                    return [
                        'id'          => (string) ($gif['id'] ?? ''),
                        'description' => mb_substr($gif['title'] ?: 'GIF', 0, 120),
                        'preview'     => $preview,
                        'url'         => $complet,
                    ];
                })
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Recherche Giphy impossible', ['exception' => $e]);

            return [];
        }
    }

    /**
     * Premier rendu disponible parmi une liste de préférences.
     */
    private function pickRendition(array $images, array $preferences): ?string
    {
        foreach ($preferences as $nom) {
            $url = $images[$nom]['url'] ?? null;

            if ($url) {
                // Giphy suffixe ses URL de paramètres de suivi : on les retire
                // pour que l'URL rapatriée reste stable et propre.
                return strtok($url, '?') ?: $url;
            }
        }

        return null;
    }
}
