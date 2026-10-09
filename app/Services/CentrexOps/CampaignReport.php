<?php

namespace App\Services\CentrexOps;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;

/**
 * Valide le contrat du fichier JSON avant toute écriture en base.
 * Aucune valeur reçue n'est incluse dans les messages d'erreur.
 */
final class CampaignReport
{
    public static function fromJson(string $raw): array
    {
        if (strlen($raw) > 8_000_000) {
            throw new InvalidArgumentException('Rapport supérieur à 8 Mio.');
        }
        try {
            $data = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Fichier JSON invalide.', 0, $exception);
        }
        if (!is_array($data) || array_is_list($data)
            || !in_array($data['schema_version'] ?? null, [1, 2], true)) {
            throw new InvalidArgumentException('Format de rapport inconnu.');
        }
        $detailed = $data['schema_version'] === 2;

        $id = $data['campaign_id'] ?? null;
        if (!is_string($id) || !preg_match(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',
            $id
        )) {
            throw new InvalidArgumentException('Identifiant de campagne invalide.');
        }
        $operation = $data['operation'] ?? null;
        if (!is_string($operation) || !in_array($operation, ['A', 'B', 'C', 'D', 'E', 'F', 'G'], true)) {
            throw new InvalidArgumentException('Opération inconnue.');
        }
        $scope = $data['scope'] ?? null;
        if (!is_string($scope) || !in_array(
            $scope,
            ['single', 'automatic_ovh', 'manual_selection', 'legacy_excerpt'],
            true
        )) {
            throw new InvalidArgumentException('Périmètre inconnu.');
        }
        $version = $data['script_version'] ?? null;
        $label = $data['operation_label'] ?? null;
        if (!is_string($version) || !preg_match('/^\d+(?:\.\d+){1,3}$/D', $version)
            || strlen($version) > 32
            || !is_string($label) || trim($label) === '' || mb_strlen($label) > 180) {
            throw new InvalidArgumentException('Version ou libellé de campagne invalide.');
        }
        $started = self::dateOrNull($data['started_at'] ?? null);
        $finished = self::dateOrNull($data['finished_at'] ?? null);
        if ($started !== null && $finished !== null && $started > $finished) {
            throw new InvalidArgumentException('Dates de campagne incohérentes.');
        }
        $versions = null;
        if ($detailed) {
            $versions = $data['component_versions'] ?? null;
            $selected = CampaignComponents::selected($operation);
            if (!is_array($versions) || array_is_list($versions)
                || array_diff(array_keys($versions), $selected) !== []
                || array_diff($selected, array_keys($versions)) !== []
                || $started === null || $finished === null) {
                throw new InvalidArgumentException('Versions ou dates des étapes manquantes.');
            }
            foreach ($versions as $key => $planned) {
                if ($key === 'webmin' && $planned === null) {
                    continue; // La version réelle provient du paquet installé.
                }
                if (!is_string($planned)
                    || !preg_match('/^\d+(?:\.\d+){1,3}$/D', $planned)
                    || strlen($planned) > 48) {
                    throw new InvalidArgumentException('Version de module invalide.');
                }
            }
        }
        $complete = $data['targets_complete'] ?? null;
        $summary = $data['summary'] ?? null;
        $results = $data['results'] ?? null;
        if (!is_bool($complete) || !is_array($summary)
            || !is_array($results) || !array_is_list($results)
            || count($results) > 5000 || count($results) === 0) {
            throw new InvalidArgumentException('Résumé ou liste des résultats invalide.');
        }
        foreach (['processed', 'success', 'failure', 'reboot_flagged'] as $key) {
            if (!isset($summary[$key]) || !is_int($summary[$key])
                || $summary[$key] < 0 || $summary[$key] > 5000) {
                throw new InvalidArgumentException('Compteurs de campagne invalides.');
            }
        }
        if (!is_bool($summary['reboot_coverage_complete'] ?? null)
            || $summary['processed'] !== $summary['success'] + $summary['failure']
            || $summary['processed'] < count($results)
            || $summary['reboot_flagged'] > $summary['processed']) {
            throw new InvalidArgumentException('Résumé de campagne incohérent.');
        }

        $knownSuccess = 0;
        $knownFailure = 0;
        $knownReboots = 0;
        $ips = [];
        $normalized = [];
        foreach ($results as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Résultat de Centrex invalide.');
            }
            $ip = $row['ip'] ?? null;
            $status = $row['status'] ?? null;
            $code = $row['code'] ?? null;
            $reboot = $row['reboot_required'] ?? null;
            $message = $row['message'] ?? null;
            $diagnostic = $row['diagnostic'] ?? null;
            if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                || isset($ips[$ip])
                || !in_array($status, ['success', 'failure'], true)
                || (!is_int($code) && $code !== null) || ($code !== null && ($code < 0 || $code > 65535))
                || ($status === 'success' && $code !== 0)
                || ($status === 'failure' && $code === 0)
                || (!is_bool($reboot) && $reboot !== null)
                || ($status === 'success' && $reboot === null)
                || !is_string($message) || mb_strlen($message) > 512
                || !is_string($diagnostic) || mb_strlen($diagnostic) > 4096) {
                throw new InvalidArgumentException('Résultat de Centrex incohérent.');
            }
            $ips[$ip] = true;
            $knownSuccess += (int) ($status === 'success');
            $knownFailure += (int) ($status === 'failure');
            $knownReboots += (int) ($reboot === true);
            $steps = [];
            if ($detailed) {
                $rawSteps = $row['steps'] ?? null;
                if (!is_array($rawSteps) || !array_is_list($rawSteps)
                    || count($rawSteps) > count($versions)) {
                    throw new InvalidArgumentException('Liste des étapes invalide.');
                }
                $seen = [];
                foreach ($rawSteps as $step) {
                    $component = is_array($step) ? ($step['component'] ?? null) : null;
                    $stepStatus = is_array($step) ? ($step['status'] ?? null) : null;
                    $stepVersion = is_array($step) ? ($step['version'] ?? null) : null;
                    if (!is_string($component) || !array_key_exists($component, $versions)
                        || isset($seen[$component])
                        || !in_array($stepStatus, ['success', 'failure', 'unknown', 'reverted'], true)
                        || ($stepVersion !== null && (!is_string($stepVersion)
                            || !preg_match('/^[0-9][0-9A-Za-z.+:~_-]{0,47}$/D', $stepVersion)))) {
                        throw new InvalidArgumentException('Étape de mise à jour invalide.');
                    }
                    $seen[$component] = true;
                    $at = self::dateOrNull($step['finished_at'] ?? null);
                    if (($stepStatus === 'success' && ($at === null || $stepVersion === null))
                        || ($stepStatus === 'failure' && $at === null)
                        || ($stepStatus === 'reverted' && ($at === null || $stepVersion !== null || $component !== 'theme'))
                        || ($stepStatus === 'unknown' && ($at !== null || $stepVersion !== null))
                        || ($component !== 'webmin' && $stepVersion !== null
                            && $stepVersion !== $versions[$component])
                        || ($at !== null && ($at < $started || $at > $finished))) {
                        throw new InvalidArgumentException('Version ou date d’étape incohérente.');
                    }
                    $steps[] = [
                        'component' => $component, 'status' => $stepStatus,
                        'version' => $stepVersion, 'observed_at' => $at,
                    ];
                }
                if ($operation === 'G') {
                    if (!isset($seen['complete_campaign'])) {
                        throw new InvalidArgumentException('Résultat de campagne complète manquant.');
                    }
                    $full = null;
                    foreach ($steps as $step) {
                        if ($step['component'] === 'complete_campaign') {
                            $full = $step;
                            break;
                        }
                    }
                    if ($status !== $full['status']) {
                        throw new InvalidArgumentException('Bilan complet incohérent.');
                    }
                }
            }
            $normalized[] = [
                'ip' => $ip,
                'status' => $status,
                'code' => $code,
                'reboot_required' => $reboot,
                'message' => $message,
                'diagnostic' => $diagnostic,
                'steps' => $steps,
            ];
        }
        if ($knownSuccess > $summary['success'] || $knownFailure > $summary['failure']
            || $knownReboots > $summary['reboot_flagged']
            || ($complete && (
                count($results) !== $summary['processed']
                || $knownSuccess !== $summary['success']
                || $knownFailure !== $summary['failure']
                || $knownReboots !== $summary['reboot_flagged']
            ))
            || ($summary['reboot_coverage_complete'] && (
                !$complete || count(array_filter(
                    $normalized,
                    static fn (array $row): bool => $row['reboot_required'] === null
                )) > 0
            ))) {
            throw new InvalidArgumentException('Résultats individuels incompatibles avec le bilan.');
        }

        return [
            'id' => strtolower($id),
            'script_version' => $version,
            'operation' => $operation,
            'operation_label' => trim($label),
            'scope' => $scope,
            'started_at' => $started,
            'finished_at' => $finished,
            'targets_complete' => $complete,
            'processed' => $summary['processed'],
            'succeeded' => $summary['success'],
            'failed' => $summary['failure'],
            'reboot_flagged' => $summary['reboot_flagged'],
            'reboot_coverage_complete' => $summary['reboot_coverage_complete'],
            'component_versions' => $versions,
            'results' => $normalized,
        ];
    }

    private static function dateOrNull(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || !preg_match(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D',
            $value
        )) {
            throw new InvalidArgumentException('Date de campagne invalide.');
        }
        $iso = str_ends_with($value, 'Z') ? substr($value, 0, -1) . '+00:00' : $value;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $iso);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false &&
            ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Date de campagne invalide.');
        }
        return $date->setTimezone(new DateTimeZone('UTC'));
    }
}
