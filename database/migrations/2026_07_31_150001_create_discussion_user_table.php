<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Frontière de lecture : tout message postérieur par quelqu'un
            // d'autre est non lu. Nulle = rien n'a encore été lu.
            $table->timestamp('last_read_at')->nullable();

            $table->timestamps();

            $table->unique(['discussion_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_user');
    }
};
