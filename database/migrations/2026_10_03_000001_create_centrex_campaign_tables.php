<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centrex_campaigns', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('script_version', 32);
            $table->char('operation', 1);
            $table->string('operation_label', 180);
            $table->string('scope', 32);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->boolean('targets_complete');
            $table->unsignedInteger('processed');
            $table->unsignedInteger('succeeded');
            $table->unsignedInteger('failed');
            $table->unsignedInteger('reboot_flagged');
            $table->boolean('reboot_coverage_complete');
            $table->char('payload_sha256', 64);
            $table->timestamps();
        });

        Schema::create('centrex_campaign_results', function (Blueprint $table): void {
            $table->id();
            $table->char('campaign_id', 36);
            $table->string('ip', 45);
            $table->string('status', 12);
            $table->unsignedSmallInteger('code')->nullable();
            $table->boolean('reboot_required')->nullable();
            $table->string('message', 512)->default('');
            $table->text('diagnostic')->nullable();
            // La correspondance avec pbx_servers est historique. Une IP seule
            // ne suffit pas si plusieurs fiches portent la même adresse.
            $table->unsignedBigInteger('pbx_server_id')->nullable();
            $table->string('matching_state', 12);
            $table->timestamps();

            $table->foreign('campaign_id')
                ->references('id')->on('centrex_campaigns')->cascadeOnDelete();
            $table->unique(['campaign_id', 'ip'], 'centrex_campaign_ip_unique');
            $table->index(['campaign_id', 'status'], 'centrex_campaign_status_index');
            $table->index(['pbx_server_id', 'campaign_id'], 'centrex_server_campaign_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centrex_campaign_results');
        Schema::dropIfExists('centrex_campaigns');
    }
};
