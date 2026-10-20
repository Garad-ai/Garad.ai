<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_ai_agent_workflow_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('ext_ai_agent_workflow_runs', 'run_context')) {
                $table->json('run_context')->nullable();
            }
            if (! Schema::hasColumn('ext_ai_agent_workflow_runs', 'current_step_order')) {
                $table->unsignedInteger('current_step_order')->nullable();
            }
        });

        Schema::create('ext_ai_agent_workflow_run_steps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workflow_run_id')->index();
            $table->unsignedInteger('step_index');
            $table->string('step_id')->nullable();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['workflow_run_id', 'step_index'], 'ext_ai_agent_run_step_order_unique');
        });

        Schema::create('ext_ai_agent_workflow_approvals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workflow_id')->index();
            $table->unsignedBigInteger('workflow_run_id')->index();
            $table->unsignedBigInteger('workflow_run_step_id')->unique();
            $table->unsignedBigInteger('requested_by')->index();
            $table->unsignedBigInteger('decision_by')->nullable();
            $table->string('status')->default('pending')->index();
            $table->json('payload')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ai_agent_workflow_approvals');
        Schema::dropIfExists('ext_ai_agent_workflow_run_steps');

        Schema::table('ext_ai_agent_workflow_runs', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('ext_ai_agent_workflow_runs', 'run_context')) {
                $columns[] = 'run_context';
            }
            if (Schema::hasColumn('ext_ai_agent_workflow_runs', 'current_step_order')) {
                $columns[] = 'current_step_order';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
