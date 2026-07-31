<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussions', function (Blueprint $table) {
            $table->id();

            // Renseigné pour les groupes uniquement : une conversation à deux
            // s'intitule du nom de l'interlocuteur, qui dépend de qui regarde.
            $table->string('name')->nullable();
            $table->boolean('is_group')->default(false);

            // Empreinte déterministe « petitId-grandId » des deux participants
            // d'une conversation directe, nulle pour les groupes. L'index unique
            // rend structurellement impossible le doublon de conversation entre
            // deux personnes, y compris si les deux l'ouvrent au même instant.
            $table->string('direct_key', 40)->nullable()->unique();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Dénormalisé pour trier la liste sans agréger les messages.
            $table->timestamp('last_message_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussions');
    }
};
