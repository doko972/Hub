<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_message_id')->constrained()->cascadeOnDelete();

            // Chemin sur le disque privé (storage/app/private) : les pièces
            // jointes ne sont jamais servies directement par le serveur web,
            // seulement par une route qui vérifie l'appartenance au fil.
            $table->string('path');

            // Nom d'origine conservé pour l'affichage et le téléchargement,
            // mais jamais utilisé comme nom de fichier sur le disque.
            $table->string('original_name');

            // Type détecté côté serveur d'après le contenu, pas d'après ce que
            // le navigateur annonce.
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_attachments');
    }
};
