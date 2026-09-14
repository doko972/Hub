<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le chemin d'administration n'a plus de valeur imposée.
     *
     * « /admin » n'est la bonne page que pour une interface FreePBX standard :
     * l'imposer envoyait le bouton « Ouvrir » sur une 404 partout ailleurs.
     * Le champ part désormais vide, et le modèle sait déjà construire une URL
     * sans chemin (voir PbxServer::url).
     *
     * doctrine/dbal n'étant pas installé, la valeur par défaut est changée en
     * SQL natif plutôt que par le Blueprint.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE pbx_servers ALTER COLUMN path SET DEFAULT ''");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE pbx_servers ALTER COLUMN path SET DEFAULT '/admin'");
        }
    }
};
