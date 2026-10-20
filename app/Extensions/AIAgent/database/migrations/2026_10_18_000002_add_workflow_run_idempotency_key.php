<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_ai_agent_workflow_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('ext_ai_agent_workflow_runs', 'idempotency_key')) {
                $table->string('idempotency_key', 128)->nullable();
                $table->unique(
                    ['workflow_id', 'idempotency_key'],
                    'ext_ai_agent_run_workflow_idempotency_unique',
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('ext_ai_agent_workflow_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('ext_ai_agent_workflow_runs', 'idempotency_key')) {
                $table->dropUnique('ext_ai_agent_run_workflow_idempotency_unique');
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
