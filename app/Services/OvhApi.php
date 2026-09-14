<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transport signé vers l'API OVHcloud.
 *
 * L'API OVH ne se contente pas d'une clé dans un en-tête : chaque requête est
 * signée avec le secret de l'application, la clé du consommateur et
 * l'horodatage du serveur OVH. Le secret ne quitte donc jamais le serveur, et
 * une requête interceptée n'est pas rejouable.
 *
 * Cette classe ne connaît aucun produit OVH : elle signe et elle transporte.
 * L'inventaire vit dans OvhInventory, les actions sur les machines dans
 * OvhMachineControl.
 */
class OvhApi
{
    /**
     * Points d'entrée de l'API, par région de compte. La région est celle du
     * compte OVH, pas celle de la machine : un compte européen pilote ses
     * instances canadiennes depuis eu.api.ovh.com.
     */
    private const ENDPOINTS = [
        'ovh-eu'        => 'https://eu.api.ovh.com/1.0',
        'ovh-ca'        => 'https://ca.api.ovh.com/1.0',
        'ovh-us'        => 'https://api.us.ovhcloud.com/1.0',
        'kimsufi-eu'    => 'https://eu.api.kimsufi.com/1.0',
        'kimsufi-ca'    => 'https://ca.api.kimsufi.com/1.0',
        'soyoustart-eu' => 'https://eu.api.soyoustart.com/1.0',
        'soyoustart-ca' => 'https://ca.api.soyoustart.com/1.0',
    ];

    private const CACHE_KEY_DECALAGE = 'ovh.horloge.decalage';

    public static function isConfigured(): bool
    {
        return filled(config('services.ovh.application_key'))
            && filled(config('services.ovh.application_secret'))
            && filled(config('services.ovh.consumer_key'))
            && isset(self::ENDPOINTS[(string) config('services.ovh.endpoint')]);
    }

    /**
     * @throws \RuntimeException
     */
    public function get(string $path): mixed
    {
        return $this->requete('GET', $path)->json();
    }

    /**
     * @throws \RuntimeException
     */
    public function getReponse(string $path): Response
    {
        return $this->requete('GET', $path);
    }

    /**
     * Appel POST signé.
     *
     * Le corps est sérialisé une seule fois : la signature porte sur les octets
     * réellement envoyés. Le re-sérialiser pour l'envoi produirait, au moindre
     * écart d'encodage, une signature valide pour un corps qui ne l'est plus.
     *
     * @param  array<string, mixed>  $corps
     *
     * @throws \RuntimeException
     */
    public function post(string $path, array $corps = []): Response
    {
        return $this->requete('POST', $path, json_encode($corps, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @throws \RuntimeException
     */
    private function requete(string $methode, string $path, ?string $corps = null): Response
    {
        $url = $this->url($path);

        try {
            $requete = Http::withHeaders($this->headers($methode, $url, $corps ?? ''))->timeout(30);

            $reponse = $corps === null
                ? $requete->get($url)
                : $requete->withBody($corps, 'application/json')->post($url);
        } catch (\Throwable $e) {
            Log::warning('OVH : API injoignable', ['path' => $path, 'message' => $e->getMessage()]);
            throw new \RuntimeException("l'API OVHcloud est injoignable.");
        }

        if (in_array($reponse->status(), [401, 403], true)) {
            Log::warning('OVH : accès refusé', [
                'methode' => $methode, 'path' => $path, 'corps' => $reponse->body(),
            ]);
            throw new \RuntimeException(
                "OVHcloud refuse l'appel « " . $methode . ' ' . $path . " » — ce droit manque au jeton."
            );
        }

        if ($reponse->failed()) {
            Log::warning('OVH : appel en échec', [
                'methode' => $methode, 'path' => $path, 'status' => $reponse->status(),
            ]);
            throw new \RuntimeException("l'API OVHcloud a répondu une erreur (" . $reponse->status() . ').');
        }

        return $reponse;
    }

    /**
     * En-têtes d'authentification OVH.
     *
     * Signature : « $1$ » + sha1(AS+CK+MÉTHODE+URL+CORPS+HORODATAGE), les
     * morceaux séparés par « + ». L'URL est complète, query string comprise.
     *
     * Publique : l'inventaire signe ses propres requêtes quand il les envoie
     * en parallèle par Http::pool.
     *
     * @return array<string, string>
     */
    public function headers(string $methode, string $url, string $corps = ''): array
    {
        $secret   = (string) config('services.ovh.application_secret');
        $consumer = (string) config('services.ovh.consumer_key');
        $horodate = (string) (time() + $this->decalageHorloge());

        $signature = '$1$' . sha1(implode('+', [
            $secret,
            $consumer,
            $methode,
            $url,
            $corps,
            $horodate,
        ]));

        return [
            'X-Ovh-Application' => (string) config('services.ovh.application_key'),
            'X-Ovh-Consumer'    => $consumer,
            'X-Ovh-Timestamp'   => $horodate,
            'X-Ovh-Signature'   => $signature,
            'Content-Type'      => 'application/json; charset=utf-8',
        ];
    }

    public function url(string $path): string
    {
        $base = self::ENDPOINTS[(string) config('services.ovh.endpoint')]
            ?? self::ENDPOINTS['ovh-eu'];

        return $base . $path;
    }

    /**
     * Écart entre l'horloge locale et celle d'OVH, en secondes.
     *
     * OVH rejette une signature dont l'horodatage dérive de plus de quelques
     * secondes. Le serveur web n'étant pas forcément à l'heure, on aligne sur
     * /auth/time — appel public, non signé, donc sans risque de boucle.
     */
    private function decalageHorloge(): int
    {
        return Cache::remember(self::CACHE_KEY_DECALAGE, 3600, function () {
            try {
                $reponse = Http::timeout(10)->get($this->url('/auth/time'));

                if ($reponse->successful() && is_numeric($reponse->body())) {
                    return (int) $reponse->body() - time();
                }
            } catch (\Throwable $e) {
                Log::warning('OVH : horloge indisponible', ['message' => $e->getMessage()]);
            }

            // Horloge locale supposée juste : c'est le cas courant, et une
            // signature refusée reste plus lisible qu'une page en erreur.
            return 0;
        });
    }
}
