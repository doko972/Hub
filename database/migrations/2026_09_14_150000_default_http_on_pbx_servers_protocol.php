<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Le protocole par défaut passe de HTTPS à HTTP.
     *
     * Les interfaces d'administration des centrex sont servies en clair sur le
     * réseau d'administration : imposer HTTPS envoyait le bouton « Ouvrir »
     * sur un port fermé. Le choix reste ouvert fiche par fiche.
     *
     * doctrine/dbal n'étant pas installé, la valeur par défaut est changée en
     * SQL natif plutôt que par le Blueprint.
     */
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE pbx_servers ALTER COLUMN protocol SET DEFAULT 'http'");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE pbx_servers ALTER COLUMN protocol SET DEFAULT 'https'");
        }
    }
};
