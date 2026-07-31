<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Une émoticone tient en quelques codets, mais certaines combinent
            // plusieurs caractères (drapeaux, teintes de peau) : on prévoit large.
            $table->string('emoji', 32);

            $table->timestamps();

            // Une même personne ne peut poser qu'une fois la même réaction sur
            // un message — la contrainte rend le double-clic inoffensif.
            $table->unique(['discussion_message_id', 'user_id', 'emoji'], 'reaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_message_reactions');
    }
};
