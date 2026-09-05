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
        Schema::table('ext_ai_agent_conversations', function (Blueprint $table) {
            $table->bigInteger('workflow_id')->nullable()->index()->after('channel_id');
        });
    }

    public function down(): void
    {
        Schema::table('ext_ai_agent_conversations', function (Blueprint $table) {
            $table->dropColumn('workflow_id');
        });
    }
};
