<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discussion_messages', function (Blueprint $table) {
            // Renseigné à la première modification : sert à afficher « modifié »
            // sans avoir à comparer created_at et updated_at, que d'autres
            // écritures peuvent faire diverger.
            $table->timestamp('edited_at')->nullable()->after('body');

            // Suppression douce plutôt que définitive : les autres participants
            // doivent apprendre qu'un message a disparu, ce qu'une ligne effacée
            // ne permet plus de signaler au sondage.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('discussion_messages', function (Blueprint $table) {
            $table->dropColumn(['edited_at', 'deleted_at']);
        });
    }
};
