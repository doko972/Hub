<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centrex_hr_ping_days', function (Blueprint $table): void {
            $table->id();
            $table->char('inventory_key', 64);
            $table->string('ip', 45);
            $table->date('day'); // UTC
            // Une position par minute UTC : ? = non mesuré, 1 = réponse, 0 = sans réponse.
            $table->text('samples');
            $table->unique(['inventory_key', 'ip', 'day'], 'centrex_hr_ping_identity_day');
            $table->index('day', 'centrex_hr_ping_retention');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centrex_hr_ping_days');
    }
};
