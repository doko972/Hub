<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thème d'interface choisi par l'utilisateur.
     *
     * 'system' = suivre le réglage de l'appareil (valeur par défaut, et
     * comportement historique de l'app avant les thèmes multiples).
     * Les valeurs possibles sont listées dans config/themes.php.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 32)->default('system')->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
