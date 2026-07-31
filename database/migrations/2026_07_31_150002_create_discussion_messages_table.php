<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_id')->constrained()->cascadeOnDelete();

            // La suppression d'un compte ne doit pas trouer les conversations
            // des autres : le message reste, son auteur devient anonyme.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');
            $table->timestamps();

            // Sert au sondage (« messages postérieurs à l'id N ») et à
            // l'affichage chronologique d'un fil.
            $table->index(['discussion_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_messages');
    }
};
