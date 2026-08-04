<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);

            // État complet du formulaire, chiffré au repos : il contient le mot
            // de passe du compte SIP et celui de l'administrateur du téléphone.
            // Un dump de la base ne doit pas livrer de quoi reconfigurer le
            // parc téléphonique.
            $table->text('payload');

            $table->timestamps();

            // Deux configurations d'un même utilisateur ne peuvent porter le
            // même nom : enregistrer à nouveau met à jour l'existante.
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_presets');
    }
};
