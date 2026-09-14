<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Inventaire des machines d'un compte OVHcloud.
 *
 * Deux familles de produits cohabitent chez OVH sous le nom courant de « VPS »,
 * et ce sont deux API distinctes :
 *
 *  - les instances Public Cloud (manager : Public Cloud › Mes projets), listées
 *    par projet, IP comprises, en un seul appel ;
 *  - les VPS Bare Metal Cloud (manager : Bare Metal Cloud › Serveurs privés
 *    virtuels), qui demandent deux appels par machine.
 *
 * Les deux sources sont interrogées et fusionnées. Une source en échec (droits
 * manquants sur le jeton, par exemple) ne fait pas échouer l'autre : elle
 * remonte un message dans « notes », affiché dans la modale d'import. Un
 * inventaire amputé en silence est ce qu'on veut éviter ici.
 *
 * La signature des requêtes est déléguée à OvhApi.
 */
class OvhInventory
{
    /** Durée de vie de l'inventaire en cache. */
    private const TTL = 600;

    /**
     * Nombre de VPS Bare Metal interrogés en parallèle. Chacun coûte deux
     * requêtes (fiche + IP) : au-delà, on empile assez d'appels simultanés
     * pour se faire plafonner par OVH. Les instances Public Cloud ne sont pas
     * concernées, un appel suffit pour tout un projet.
     */
    private const LOT = 8;

    private const CACHE_KEY = 'ovh.inventaire';

    public function __construct(private readonly OvhApi $api)
    {
    }

    public static function isConfigured(): bool
    {
        return OvhApi::isConfigured();
    }

    /**
     * Inventaire complet : ['machines' => [...], 'notes' => [...]].
     *
     * Chaque machine porte : ref, kind, name, ipv4, ipv6, state, zone, group,
     * project. « ref » est l'identifiant stable côté OVH (id d'instance ou nom
     * de service VPS) — c'est lui, et lui seul, que l'import accepte du
     * navigateur. « project » est l'id du projet Public Cloud, nécessaire pour
     * agir ensuite sur la machine ; il est vide pour un VPS Bare Metal.
     *
     * @param  bool  $fresh  Ignore le cache (bouton « Actualiser » de la modale).
     * @return array{machines: array<int, array<string, mixed>>, notes: array<int, string>}
     *
     * @throws \RuntimeException si aucune source ne répond.
     */
    public function machines(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::TTL, fn () => $this->collecter());
    }

    /**
     * @return array{machines: array<int, array<string, mixed>>, notes: array<int, string>}
     */
    private function collecter(): array
    {
        $machines = [];
        $notes    = [];
        $echecs   = 0;

        // Chaque source rend ses machines et ses propres avertissements :
        // pas de tableau partagé, que des valeurs de retour.
        try {
            [$trouvees, $remontees] = $this->instancesPublicCloud();

            $machines = array_merge($machines, $trouvees);
            $notes    = array_merge($notes, $remontees);
        } catch (\RuntimeException $e) {
            $echecs++;
            $notes[] = 'Public Cloud : ' . $e->getMessage();
        }

        try {
            $machines = array_merge($machines, $this->vpsBareMetal());
        } catch (\RuntimeException $e) {
            $echecs++;
            $notes[] = 'VPS : ' . $e->getMessage();
        }

        // Les deux sources muettes : c'est une panne, pas un compte vide.
        if ($echecs === 2) {
            throw new \RuntimeException(implode(' ', $notes));
        }

        usort($machines, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return ['machines' => $machines, 'notes' => $notes];
    }

    // -----------------------------------------------------------------
    // Public Cloud — manager : Public Cloud › Mes projets › Instances
    // -----------------------------------------------------------------

    /**
     * Instances de tous les projets Public Cloud, ou des seuls projets listés
     * dans OVH_CLOUD_PROJECTS.
     *
     * Rend ses machines et ses avertissements : un projet illisible ou une
     * liste tronquée doivent se voir, pas disparaître.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function instancesPublicCloud(): array
    {
        $projets = (array) $this->api->get('/cloud/project');
        $retenus = $this->projetsRetenus();
        $notes   = [];

        if ($retenus !== []) {
            $projets = array_values(array_intersect($projets, $retenus));
        }

        $machines = [];

        // Peu de projets en général, et un seul appel suffit pour toutes les
        // instances de chacun : la boucle séquentielle reste lisible et rapide.
        foreach ($projets as $projet) {
            $projet = (string) $projet;

            try {
                $instances = $this->api->getReponse('/cloud/project/' . rawurlencode($projet) . '/instance');
            } catch (\RuntimeException $e) {
                $notes[] = 'Projet ' . $projet . ' : ' . $e->getMessage();
                continue;
            }

            $liste = (array) $instances->json();

            // Garde-fou : si OVH annonce plus d'éléments qu'il n'en a renvoyé,
            // l'inventaire est tronqué et il ne faut surtout pas le taire.
            $annonces = $instances->header('x-pagination-elements');
            if (is_numeric($annonces) && (int) $annonces > count($liste)) {
                $notes[] = 'Projet ' . $projet . ' : OVH annonce ' . (int) $annonces
                    . ' instances mais n\'en a renvoyé que ' . count($liste) . '.';
                Log::warning('OVH : liste d\'instances tronquée', [
                    'projet' => $projet, 'annonces' => (int) $annonces, 'recus' => count($liste),
                ]);
            }

            $nomProjet = $this->nomProjet($projet);

            foreach ($liste as $instance) {
                if (! is_array($instance)) {
                    continue;
                }

                $machines[] = [
                    'kind'    => 'instance',
                    'ref'     => (string) ($instance['id'] ?? ''),
                    'project' => $projet,
                    'name'    => $this->nettoyer($instance['name'] ?? null) ?: (string) ($instance['id'] ?? ''),
                    'ipv4'    => $this->ipPublique($instance['ipAddresses'] ?? [], 4),
                    'ipv6'    => $this->ipPublique($instance['ipAddresses'] ?? [], 6),
                    'state'   => $this->nettoyer($instance['status'] ?? null),
                    'zone'    => $this->nettoyer($instance['region'] ?? null),
                    'group'   => $nomProjet,
                ];
            }
        }

        return [
            array_values(array_filter($machines, fn ($m) => $m['ref'] !== '')),
            $notes,
        ];
    }

    /**
     * Nom lisible d'un projet. L'id seul (32 caractères hexadécimaux) ne dit
     * rien à personne ; en cas d'échec on s'en contente tout de même.
     */
    private function nomProjet(string $projet): string
    {
        try {
            $detail = (array) $this->api->get('/cloud/project/' . rawurlencode($projet));
        } catch (\RuntimeException) {
            return $projet;
        }

        return $this->nettoyer($detail['description'] ?? null)
            ?? $this->nettoyer($detail['projectName'] ?? null)
            ?? $projet;
    }

    /**
     * Projets Public Cloud à parcourir, depuis OVH_CLOUD_PROJECTS.
     * Vide = tous les projets du compte.
     *
     * @return array<int, string>
     */
    private function projetsRetenus(): array
    {
        $brut = (string) config('services.ovh.cloud_projects', '');

        return array_values(array_filter(array_map('trim', explode(',', $brut))));
    }

    /**
     * Première IP publique de la version demandée.
     *
     * Une instance porte aussi ses IP privées de réseau vRack : les importer
     * donnerait une fiche centrex injoignable depuis le Hub.
     */
    private function ipPublique(mixed $adresses, int $version): ?string
    {
        foreach ((array) $adresses as $adresse) {
            if (! is_array($adresse)
                || ($adresse['type'] ?? null) !== 'public'
                || (int) ($adresse['version'] ?? 0) !== $version) {
                continue;
            }

            $ip = explode('/', trim((string) ($adresse['ip'] ?? '')))[0];

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    // VPS — manager : Bare Metal Cloud › Serveurs privés virtuels
    // -----------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vpsBareMetal(): array
    {
        $services = (array) $this->api->get('/vps');
        $vps = [];

        // Par lots : la fiche et les IP de chaque VPS partent ensemble, mais on
        // ne lance pas les 2N requêtes d'un seul coup.
        foreach (array_chunk($services, self::LOT) as $lot) {
            $reponses = Http::pool(function (Pool $pool) use ($lot) {
                $requetes = [];

                foreach ($lot as $service) {
                    foreach (['fiche' => '', 'ips' => '/ips'] as $cle => $suffixe) {
                        $url = $this->api->url('/vps/' . rawurlencode((string) $service) . $suffixe);

                        $requetes[] = $pool->as($cle . '|' . $service)
                            ->withHeaders($this->api->headers('GET', $url))
                            ->timeout(15)
                            ->get($url);
                    }
                }

                return $requetes;
            });

            foreach ($lot as $service) {
                $fiche = $reponses['fiche|' . $service] ?? null;
                $ips   = $reponses['ips|' . $service] ?? null;

                // Un VPS qui répond mal ne doit pas vider toute la liste :
                // on le laisse de côté et on continue.
                if (! $fiche instanceof Response || $fiche->failed()) {
                    Log::warning('OVH : fiche VPS illisible', ['service' => $service]);
                    continue;
                }

                $detail = (array) $fiche->json();
                $listeIps = ($ips instanceof Response && $ips->successful()) ? (array) $ips->json() : [];

                $vps[] = [
                    'kind'    => 'vps',
                    'ref'     => (string) $service,
                    'project' => null,
                    'name'    => $this->nettoyer($detail['displayName'] ?? null) ?: (string) $service,
                    'ipv4'    => $this->premiereIp($listeIps, FILTER_FLAG_IPV4),
                    'ipv6'    => $this->premiereIp($listeIps, FILTER_FLAG_IPV6),
                    'state'   => $this->nettoyer($detail['state'] ?? null),
                    'zone'    => $this->nettoyer($detail['zone'] ?? null),
                    'group'   => 'VPS',
                ];
            }
        }

        return $vps;
    }

    /**
     * Première adresse de la famille demandée.
     *
     * OVH renvoie aussi bien « 51.75.x.x » qu'un bloc « 2001:41d0:…::/64 » :
     * le masque est retiré avant validation, sinon filter_var rejette tout.
     *
     * @param  array<int, mixed>  $ips
     */
    private function premiereIp(array $ips, int $famille): ?string
    {
        foreach ($ips as $ip) {
            if (! is_string($ip)) {
                continue;
            }

            $adresse = explode('/', trim($ip))[0];

            if (filter_var($adresse, FILTER_VALIDATE_IP, $famille)) {
                return $adresse;
            }
        }

        return null;
    }

    private function nettoyer(mixed $valeur): ?string
    {
        if (! is_string($valeur)) {
            return null;
        }

        $valeur = trim($valeur);

        return $valeur === '' ? null : $valeur;
    }
}
