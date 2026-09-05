<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ext_ai_agent_workflows', function (Blueprint $table) {
            $table->string('copilot_model')->nullable()->after('system_instructions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_ai_agent_workflows', function (Blueprint $table) {
            $table->dropColumn('copilot_model');
        });
    }
};
