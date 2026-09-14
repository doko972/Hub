<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pbx_servers', function (Blueprint $table) {
            // Projet Public Cloud auquel appartient l'instance.
            //
            // L'id d'instance seul ne suffit pas à agir sur la machine :
            // l'API OVH veut le couple projet + instance
            // (POST /cloud/project/{projet}/instance/{id}/reboot).
            //
            // Vide pour les VPS Bare Metal, qui n'appartiennent à aucun projet,
            // et pour les fiches saisies à la main.
            $table->string('ovh_project', 190)->nullable()->after('ovh_service_name');
        });
    }

    public function down(): void
    {
        Schema::table('pbx_servers', function (Blueprint $table) {
            $table->dropColumn('ovh_project');
        });
    }
};
