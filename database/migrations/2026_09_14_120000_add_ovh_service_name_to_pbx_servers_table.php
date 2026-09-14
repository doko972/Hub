<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pbx_servers', function (Blueprint $table) {
            // Nom de service OVH de la VM d'origine (« vpsXXXXX.ovh.net »),
            // renseigné par l'import depuis l'API OVHcloud.
            //
            // Il sert à reconnaître un VPS déjà référencé : l'IP, elle, peut
            // changer côté OVH ou avoir été corrigée à la main, alors que le
            // nom de service ne bouge pas de la vie de la machine.
            //
            // Nullable et sans contrainte d'unicité : les centrex saisis à la
            // main n'en ont pas, et rien n'interdit deux fiches sur une même
            // machine (interface admin et portail utilisateur, par exemple).
            $table->string('ovh_service_name', 190)->nullable()->after('host');
            $table->index('ovh_service_name');
        });
    }

    public function down(): void
    {
        Schema::table('pbx_servers', function (Blueprint $table) {
            $table->dropIndex(['ovh_service_name']);
            $table->dropColumn('ovh_service_name');
        });
    }
};
