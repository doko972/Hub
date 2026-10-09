<?php

namespace App\Services\CentrexOps;

use UnexpectedValueException;

/** Prépare une lecture complète d'OVH pour la copie locale, sans requête SQL. */
final class OvhSnapshot
{
    public static function assertPlausibleCount(int $previous, int $received): void
    {
        if ($previous >= 20 && $received < (int) ceil($previous * 0.8)) {
            throw new UnexpectedValueException(
                'OVH a renvoyé beaucoup moins de machines que lors de la dernière lecture. La liste enregistrée a été conservée ; vérifiez la réponse OVH.'
            );
        }
    }

    /**
     * @param array<string, mixed> $inventory
     * @param array<string, string> $knownFirstSeen
     * @return list<array<string, string|int|null>>
     */
    public static function prepare(array $inventory, array $knownFirstSeen, string $now): array
    {
        if (!empty($inventory['notes']) || !empty($inventory['incomplete'])) {
            throw new UnexpectedValueException(
                'OVH a renvoyé une liste partielle. La liste enregistrée a été conservée.'
            );
        }

        $machines = OvhFleet::normalize($inventory);
        if ($machines === []) {
            throw new UnexpectedValueException(
                'OVH n’a renvoyé aucune machine. La liste enregistrée a été conservée.'
            );
        }

        $rows = [];
        foreach ($machines as $machine) {
            $key = hash('sha256', $machine['key']);
            $rows[] = [
                'inventory_key' => $key,
                'name' => $machine['name'],
                'ip' => $machine['ip'],
                'ovh_ref' => $machine['ref'],
                'ovh_project' => $machine['project'],
                'ovh_state' => $machine['state'],
                'ovh_zone' => $machine['zone'],
                'is_present' => 1,
                'first_seen_at' => $knownFirstSeen[$key] ?? $now,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
