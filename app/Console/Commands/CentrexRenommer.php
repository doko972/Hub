<?php

namespace App\Console\Commands;

use App\Models\PbxServer;
use Illuminate\Console\Command;

/**
 * Applique la règle de nommage lisible aux fiches déjà en base.
 *
 * L'import nettoie désormais les noms au vol (PbxServer::nomLisible), mais les
 * fiches créées avant portent encore le nom brut de la machine OVH. Cette
 * commande rattrape l'existant.
 *
 * --dry-run affiche les renommages sans rien écrire : sur près de 300 fiches,
 * on veut voir le résultat avant de le subir.
 */
class CentrexRenommer extends Command
{
    protected $signature = 'centrex:renommer
                            {--dry-run : Affiche les renommages sans les appliquer}
                            {--limit=20 : Nombre d\'exemples affichés}';

    protected $description = 'Nettoie les noms des centrex (préfixe IPBX retiré, underscores en espaces)';

    public function handle(): int
    {
        $exemples = [];
        $aChanger = 0;
        $vus      = 0;
        $limite   = (int) $this->option('limit');
        $sec      = (bool) $this->option('dry-run');

        PbxServer::query()->orderBy('name')->chunkById(200, function ($fiches) use (&$exemples, &$aChanger, &$vus, $limite, $sec) {
            foreach ($fiches as $fiche) {
                $vus++;
                $nouveau = mb_substr(PbxServer::nomLisible($fiche->name), 0, 80);

                if ($nouveau === $fiche->name) {
                    continue;
                }

                $aChanger++;

                if (count($exemples) < $limite) {
                    $exemples[] = [$fiche->name, $nouveau];
                }

                if (! $sec) {
                    $fiche->name = $nouveau;
                    $fiche->save();
                }
            }
        });

        if ($exemples !== []) {
            $this->table(['Avant', 'Après'], $exemples);
        }

        $this->line($vus . ' fiche(s) examinée(s).');

        $this->info($sec
            ? $aChanger . ' fiche(s) seraient renommée(s).'
            : $aChanger . ' fiche(s) renommée(s).');

        return self::SUCCESS;
    }
}
