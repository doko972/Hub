<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->foreignId('tool_family_id')
                  ->nullable()
                  ->after('sort_order')
                  ->constrained('tool_families')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropForeign(['tool_family_id']);
            $table->dropColumn('tool_family_id');
        });
    }
};
