<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_ai_agent_workflow_copilot_messages', function (Blueprint $table): void {
            $table->json('metadata')->nullable()->after('tool_calls');
        });
    }

    public function down(): void
    {
        Schema::table('ext_ai_agent_workflow_copilot_messages', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });
    }
};
