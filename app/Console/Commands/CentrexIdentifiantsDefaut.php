<?php

namespace App\Console\Commands;

use App\Models\PbxServer;
use Illuminate\Console\Command;

/**
 * Pose les identifiants d'usine sur les centrex qui n'en ont pas.
 *
 * Utile après un import réalisé avant que CENTREX_DEFAULT_LOGIN et
 * CENTREX_DEFAULT_PASSWORD ne soient renseignés, ou après une rotation du
 * couple par défaut sur des fiches encore vierges.
 *
 * Par défaut, ne touche qu'aux fiches dépourvues d'identifiant : un mot de
 * passe saisi à la main sur un centrex précis ne doit pas être écrasé par une
 * commande de masse. --force lève cette protection, explicitement.
 */
class CentrexIdentifiantsDefaut extends Command
{
    protected $signature = 'centrex:identifiants-defaut
                            {--force : Écrase aussi les identifiants déjà renseignés}
                            {--dry-run : Affiche ce qui serait modifié, sans rien écrire}';

    protected $description = "Applique les identifiants par défaut aux fiches centrex qui n'en ont pas";

    public function handle(): int
    {
        $login    = (string) config('services.centrex.default_login', '');
        $password = (string) config('services.centrex.default_password', '');

        if ($login === '' && $password === '') {
            $this->error('CENTREX_DEFAULT_LOGIN et CENTREX_DEFAULT_PASSWORD sont vides.');
            $this->line('Renseignez-les dans le .env, puis lancez « php artisan config:clear ».');

            return self::FAILURE;
        }

        $requete = PbxServer::query();

        if (! $this->option('force')) {
            $requete->whereNull('login')->whereNull('password');
        }

        $total = (clone $requete)->count();

        if ($total === 0) {
            $this->info('Aucune fiche à compléter.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info($total . ' fiche(s) seraient complétées.');

            return self::SUCCESS;
        }

        // Le mot de passe est chiffré par le cast du modèle : il faut passer
        // par save(), pas par un update() de masse en SQL.
        $faites = 0;

        $requete->chunkById(200, function ($fiches) use ($login, $password, &$faites) {
            foreach ($fiches as $fiche) {
                $fiche->login    = $login !== '' ? $login : $fiche->login;
                $fiche->password = $password !== '' ? $password : $fiche->password;
                $fiche->save();
                $faites++;
            }
        });

        $this->info($faites . ' fiche(s) complétée(s).');

        return self::SUCCESS;
    }
}
