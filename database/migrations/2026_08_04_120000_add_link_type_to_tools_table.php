<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            // « web » : une adresse que le navigateur sait ouvrir.
            // « path » : un chemin réseau, que le navigateur refuse d'ouvrir
            // depuis une page https — la vignette le copie alors dans le
            // presse-papier plutôt que de proposer un lien mort.
            $table->string('link_type', 10)->default('web')->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn('link_type');
        });
    }
};
