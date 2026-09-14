<?php

namespace App\Services;

use App\Models\PbxServer;
use Illuminate\Support\Facades\Cache;

/**
 * Actions sur les machines OVHcloud.
 *
 * Seul endroit du projet qui écrit chez OVH. Tout le reste de l'intégration
 * est en lecture, et le jeton doit le rester au maximum : le droit à accorder
 * est « POST /cloud/project/*&#47;instance/*&#47;reboot », surtout pas
 * « POST /cloud/project/* », qui autoriserait aussi la création d'instances
 * facturées et la réinstallation d'une machine.
 *
 * Le redémarrage est volontairement le seul verbe exposé : un centrex éteint
 * par mégarde ne se rallume pas tout seul, et couper la téléphonie d'un client
 * ne doit pas tenir à un clic de trop.
 */
class OvhMachineControl
{
    public function __construct(private readonly OvhApi $api)
    {
    }

    public static function isConfigured(): bool
    {
        return OvhApi::isConfigured();
    }

    /**
     * Une fiche est pilotable si elle sait désigner sa machine chez OVH :
     * projet + instance pour le Public Cloud, nom de service pour un VPS.
     */
    public static function pilotable(PbxServer $centrex): bool
    {
        return filled($centrex->ovh_service_name);
    }

    /**
     * Redémarrage en douceur : OVH demande au système de s'arrêter proprement,
     * il ne coupe pas l'alimentation. Les communications en cours tombent tout
     * de même — c'est inhérent au redémarrage d'un PBX, pas au mode choisi.
     *
     * @throws \RuntimeException si OVH refuse l'appel ou si la fiche ne désigne
     *                           aucune machine.
     */
    public function redemarrer(PbxServer $centrex): void
    {
        if (! self::pilotable($centrex)) {
            throw new \RuntimeException("Cette fiche n'est rattachée à aucune machine OVHcloud.");
        }

        if (filled($centrex->ovh_project)) {
            $this->api->post(
                '/cloud/project/' . rawurlencode($centrex->ovh_project)
                    . '/instance/' . rawurlencode($centrex->ovh_service_name) . '/reboot',
                ['type' => 'soft'],
            );
        } else {
            // VPS Bare Metal : pas de projet, et le mode de redémarrage se
            // passe de corps de requête.
            $this->api->post('/vps/' . rawurlencode($centrex->ovh_service_name) . '/reboot');
        }

        // L'état renvoyé par l'inventaire vient de se périmer : la machine
        // affichée « ACTIVE » est en train de redémarrer. Le cache repart donc,
        // pour que la prochaine ouverture de la modale dise la vérité.
        Cache::forget('ovh.inventaire');
    }
}
