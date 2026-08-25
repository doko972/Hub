<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pbx_servers', function (Blueprint $table) {
            $table->id();

            $table->string('name', 80);
            $table->string('client', 120)->nullable();

            // Adresse de la VM : protocole + hôte (IP ou nom) + port + chemin
            // d'administration. L'URL n'est jamais stockée telle quelle : elle
            // est reconstruite par le modèle, ce qui interdit d'y glisser un
            // schéma exotique (javascript:, data:…).
            $table->string('protocol', 5)->default('https');
            $table->string('host', 190);
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('path', 120)->default('/admin');

            $table->string('login', 190)->nullable();

            // Chiffré au repos via le cast 'encrypted' (AES-256, APP_KEY) :
            // un dump de la base ne doit pas livrer l'accès aux centrex.
            $table->text('password')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            // Trace de l'auteur : la liste est partagée, savoir qui a ajouté
            // quoi évite les fiches orphelines sans interlocuteur.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pbx_servers');
    }
};
