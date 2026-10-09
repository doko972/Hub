<?php

namespace App\Services\CentrexOps;

final class CampaignComponents
{
    public const LABELS = [
        'freepbx_modules' => 'Modules FreePBX',
        'system_packages' => 'Paquets système',
        'webmin' => 'Webmin',
        'theme' => 'Thème HR Télécoms',
        'topology_hr' => 'Topologie HR',
        'statistics_hr' => 'Statistiques HR',
        'diagnostic_hr' => 'Diagnostic HR',
        'complete_campaign' => 'Mise à jour complète',
    ];

    public static function selected(string $operation): array
    {
        return match ($operation) {
            'A' => ['freepbx_modules', 'theme', 'diagnostic_hr'],
            'B' => ['system_packages', 'webmin', 'theme', 'diagnostic_hr'],
            'C' => ['system_packages', 'webmin', 'freepbx_modules', 'theme', 'diagnostic_hr'],
            'D' => ['theme', 'diagnostic_hr'],
            'E' => ['topology_hr'],
            'F' => ['statistics_hr'],
            'G' => array_keys(self::LABELS),
            default => [],
        };
    }

    public static function versionType(string $component): string
    {
        return match ($component) {
            'webmin' => 'Version du paquet constatée',
            'freepbx_modules', 'system_packages', 'complete_campaign' => 'Version de la procédure',
            default => 'Version du module ou du thème fourni',
        };
    }
}
