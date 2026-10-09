<?php

namespace App\Console\Commands;

use App\Models\CentrexCampaign;
use App\Models\CentrexCampaignResult;
use App\Models\PbxServer;
use App\Services\CentrexOps\CampaignReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ImportCentrexCampaign extends Command
{
    protected $signature = 'centrex:import-campaign {file : Chemin du rapport JSON local}';
    protected $description = 'Importer un bilan de campagne dans les tables Centrex-HR du Hub';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (!is_file($path) || is_link($path) || !is_readable($path)
            || filesize($path) === false || filesize($path) > 8_000_000) {
            $this->error('Fichier absent, illisible, lien symbolique ou supérieur à 8 Mio.');
            return self::FAILURE;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error('Lecture du rapport impossible.');
            return self::FAILURE;
        }
        try {
            $report = CampaignReport::fromJson($raw);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $checksum = hash('sha256', $raw);
        $previous = CentrexCampaign::find($report['id']);
        if ($previous !== null) {
            if (hash_equals($previous->payload_sha256, $checksum)) {
                $this->info('Rapport déjà importé, sans écriture supplémentaire.');
                return self::SUCCESS;
            }
            $this->error('Cet identifiant de campagne existe avec un contenu différent.');
            return self::FAILURE;
        }

        // On ne rapproche que les adresses IPv4 exactement égales à host.
        // Aucun DNS, nom client ni préfixe commun ne sert à deviner une fiche.
        $hosts = collect();
        foreach (array_chunk(array_column($report['results'], 'ip'), 500) as $ips) {
            $hosts = $hosts->concat(
                PbxServer::query()->whereIn('host', $ips)->get(['id', 'host'])
            );
        }
        $hosts = $hosts->groupBy('host');
        $unmatched = 0;
        $ambiguous = 0;
        try {
            DB::transaction(function () use (
                $report, $checksum, $hosts, &$unmatched, &$ambiguous
            ): void {
                CentrexCampaign::create([
                    'id' => $report['id'],
                    'script_version' => $report['script_version'],
                    'operation' => $report['operation'],
                    'operation_label' => $report['operation_label'],
                    'scope' => $report['scope'],
                    'started_at' => $report['started_at'],
                    'finished_at' => $report['finished_at'],
                    'targets_complete' => $report['targets_complete'],
                    'processed' => $report['processed'],
                    'succeeded' => $report['succeeded'],
                    'failed' => $report['failed'],
                    'reboot_flagged' => $report['reboot_flagged'],
                    'reboot_coverage_complete' => $report['reboot_coverage_complete'],
                    'payload_sha256' => $checksum,
                    'component_versions' => $report['component_versions'],
                ]);
                foreach ($report['results'] as $item) {
                    $matches = $hosts->get($item['ip'], collect());
                    $state = match ($matches->count()) {
                        0 => 'none',
                        1 => 'unique',
                        default => 'ambiguous',
                    };
                    $unmatched += (int) ($state === 'none');
                    $ambiguous += (int) ($state === 'ambiguous');
                    $result = CentrexCampaignResult::create([
                        'campaign_id' => $report['id'],
                        'ip' => $item['ip'],
                        'status' => $item['status'],
                        'code' => $item['code'],
                        'reboot_required' => $item['reboot_required'],
                        'message' => $item['message'],
                        'diagnostic' => $item['diagnostic'],
                        'pbx_server_id' => $state === 'unique' ? $matches->first()->id : null,
                        'matching_state' => $state,
                    ]);
                    if ($item['steps'] !== []) {
                        $result->steps()->createMany($item['steps']);
                    }
                }
            });
        } catch (Throwable $exception) {
            // Ne jamais imprimer la requête SQL : elle pourrait contenir un
            // diagnostic fourni par la machine distante.
            $this->error('Import annulé : erreur de base ou de correspondance. Aucune écriture partielle.');
            return self::FAILURE;
        }
        $this->info(
            "Campagne importée : {$report['processed']} traités, " .
            "{$report['failed']} échecs, {$unmatched} sans fiche et " .
            "{$ambiguous} correspondances ambiguës parmi les résultats détaillés."
        );
        if (!$report['targets_complete']) {
            $this->warn('Rapport partiel : des succès peuvent ne pas avoir de ligne individuelle.');
        }
        return self::SUCCESS;
    }
}
