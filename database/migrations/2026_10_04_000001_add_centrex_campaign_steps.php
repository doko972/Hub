<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centrex_campaigns', function (Blueprint $table): void {
            $table->json('component_versions')->nullable();
        });

        Schema::create('centrex_campaign_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_result_id')
                ->constrained('centrex_campaign_results')->cascadeOnDelete();
            $table->string('component', 32);
            $table->string('status', 12);
            $table->string('version', 48)->nullable();
            $table->dateTime('observed_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_result_id', 'component'], 'centrex_step_result_component_unique');
            $table->index(['component', 'status', 'observed_at'], 'centrex_step_component_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centrex_campaign_steps');
        Schema::table('centrex_campaigns', function (Blueprint $table): void {
            $table->dropColumn('component_versions');
        });
    }
};
