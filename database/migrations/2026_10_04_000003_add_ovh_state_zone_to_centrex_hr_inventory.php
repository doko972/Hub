<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centrex_hr_ovh_machines', function (Blueprint $table): void {
            $table->string('ovh_state', 64)->nullable();
            $table->string('ovh_zone', 96)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('centrex_hr_ovh_machines', function (Blueprint $table): void {
            $table->dropColumn(['ovh_state', 'ovh_zone']);
        });
    }
};
