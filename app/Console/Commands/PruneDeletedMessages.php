<?php

namespace App\Console\Commands;

use App\Models\DiscussionMessage;
use Illuminate\Console\Command;

/**
 * Efface définitivement les messages supprimés depuis un certain temps.
 *
 * La suppression est douce afin que le sondage puisse annoncer la disparition
 * aux autres participants. Passé quelques jours, plus personne n'a la bulle à
 * l'écran : la trace n'a plus d'utilité et n'est plus que du poids en base.
 */
class PruneDeletedMessages extends Command
{
    protected $signature = 'messages:prune
                            {--days=30 : Âge minimal, en jours, des traces à effacer}
                            {--dry-run : Compter sans rien supprimer}';

    protected $description = 'Efface définitivement les messages supprimés de longue date';

    public function handle(): int
    {
        $jours = max(1, (int) $this->option('days'));
        $seuil = now()->subDays($jours);

        $requete = DiscussionMessage::onlyTrashed()->where('deleted_at', '<', $seuil);
        $total   = $requete->count();

        if ($total === 0) {
            $this->info("Aucune trace de plus de {$jours} jours.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$total} trace(s) seraient effacées (plus de {$jours} jours).");

            return self::SUCCESS;
        }

        // Suppression par lots via le modèle : les évènements Eloquent doivent
        // se déclencher pour que d'éventuelles pièces jointes quittent le
        // disque. Une suppression en masse les contournerait.
        $efface = 0;

        $requete->chunkById(200, function ($messages) use (&$efface) {
            foreach ($messages as $message) {
                $message->forceDelete();
                $efface++;
            }
        });

        $this->info("{$efface} trace(s) effacée(s) définitivement.");

        return self::SUCCESS;
    }
}
