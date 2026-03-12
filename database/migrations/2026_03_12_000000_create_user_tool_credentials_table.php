<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tool_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->string('login')->nullable();
            $table->text('password')->nullable(); // chiffré via cast 'encrypted'
            $table->timestamps();

            $table->unique(['user_id', 'tool_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tool_credentials');
    }
};
