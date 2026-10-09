<?php

namespace App\Services\CentrexOps;

use UnexpectedValueException;

/**
 * Adapte la réponse du service OVH déjà présent dans le Hub à la liste HR.
 * Aucune fiche n'est écrite et aucune machine n'est déduite de la base locale.
 */
final class OvhFleet
{
    /** @return list<array{key: string, name: string, ip: ?string, ref: string, project: ?string, state: ?string, zone: ?string}> */
    public static function normalize(array $inventory): array
    {
        $machines = array_key_exists('machines', $inventory)
            ? $inventory['machines']
            : $inventory;
        if (!is_array($machines)) {
            throw new UnexpectedValueException('Format de l’inventaire OVH inconnu.');
        }

        $rows = [];
        $seen = [];
        foreach ($machines as $machine) {
            if (!is_array($machine) || !is_string($machine['ref'] ?? null)
                || trim($machine['ref']) === '') {
                throw new UnexpectedValueException('Machine OVH sans référence exploitable.');
            }
            $ref = trim($machine['ref']);
            $project = is_string($machine['project'] ?? null)
                ? trim($machine['project']) : '';
            $key = $project . "\0" . $ref;
            if (isset($seen[$key])) {
                throw new UnexpectedValueException('Référence OVH présente plusieurs fois.');
            }
            $seen[$key] = true;

            $name = is_string($machine['name'] ?? null)
                ? trim($machine['name']) : '';
            // OvhInventory expose « ipv4 » ; « ip » reste accepté pour un
            // éventuel ancien contrat de ce même service.
            $rawIp = array_key_exists('ipv4', $machine)
                ? $machine['ipv4'] : ($machine['ip'] ?? null);
            $ip = is_string($rawIp) && filter_var($rawIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                ? $rawIp : null;
            $rows[] = [
                'key' => $key,
                'name' => $name !== '' ? $name : $ref,
                'ip' => $ip,
                'ref' => $ref,
                'project' => $project !== '' ? $project : null,
                'state' => self::optionalText($machine['state'] ?? null, 64),
                'zone' => self::optionalText($machine['zone'] ?? null, 96),
            ];
        }

        return $rows;
    }

    /** Une valeur nouvelle ou inconnue reste visible, sans lui prêter un sens. */
    public static function displayState(?string $state): array
    {
        if ($state === null || $state === '') {
            return ['label' => 'Non renseigné', 'tone' => 'unknown'];
        }

        return match (strtolower($state)) {
            'active', 'running' => ['label' => 'Active chez OVH', 'tone' => 'good'],
            'stopped', 'off' => ['label' => 'Arrêtée chez OVH', 'tone' => 'danger'],
            'error' => ['label' => 'Erreur chez OVH', 'tone' => 'danger'],
            default => ['label' => $state, 'tone' => 'unknown'],
        };
    }

    private static function optionalText(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return null;
        }

        // Le motif Unicode refuse une chaîne invalide et coupe sans casser
        // un caractère multioctet avant son stockage en varchar.
        return preg_match('/\A.{1,' . $maxLength . '}/us', $value, $matches) === 1
            ? $matches[0] : null;
    }
}
