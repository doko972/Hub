<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discussion_user', function (Blueprint $table) {
            // Suppression d'une conversation, propre à chaque participant : les
            // messages d'identifiant inférieur ou égal ne lui sont plus montrés.
            // Un identifiant plutôt qu'une date : deux messages de la même
            // seconde ne peuvent pas tomber du mauvais côté de la frontière.
            // Nulle = rien n'a été supprimé.
            $table->unsignedBigInteger('cleared_through_id')->nullable()->after('last_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('discussion_user', function (Blueprint $table) {
            $table->dropColumn('cleared_through_id');
        });
    }
};
