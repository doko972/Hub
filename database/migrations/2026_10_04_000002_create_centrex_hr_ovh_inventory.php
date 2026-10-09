<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Copie locale de l'inventaire OVH, indépendante des fiches pbx_servers.
        Schema::create('centrex_hr_ovh_machines', function (Blueprint $table): void {
            $table->id();
            $table->char('inventory_key', 64)->unique();
            $table->text('name');
            $table->string('ip', 45)->nullable();
            $table->text('ovh_ref');
            $table->text('ovh_project')->nullable();
            $table->boolean('is_present')->default(true);
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->timestamps();
            $table->index(['is_present', 'first_seen_at'], 'centrex_hr_ovh_presence_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centrex_hr_ovh_machines');
    }
};
