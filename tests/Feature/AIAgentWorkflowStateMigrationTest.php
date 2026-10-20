<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AIAgentWorkflowStateMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default'                     => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('ext_ai_agent_workflow_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workflow_id')->nullable();
            $table->string('status')->default('running');
            $table->json('trigger_data')->nullable();
            $table->json('steps_output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_workflow_state_migrations_add_step_approval_and_idempotency_persistence(): void
    {
        $stateMigration = require base_path(
            'app/Extensions/AIAgent/database/migrations/2026_10_18_000001_add_persisted_step_and_approval_state.php',
        );
        $stateMigration->up();

        $idempotencyMigration = require base_path(
            'app/Extensions/AIAgent/database/migrations/2026_10_18_000002_add_workflow_run_idempotency_key.php',
        );
        $idempotencyMigration->up();

        $this->assertTrue(Schema::hasColumn('ext_ai_agent_workflow_runs', 'run_context'));
        $this->assertTrue(Schema::hasColumn('ext_ai_agent_workflow_runs', 'current_step_order'));
        $this->assertTrue(Schema::hasColumn('ext_ai_agent_workflow_runs', 'idempotency_key'));
        $this->assertTrue(Schema::hasTable('ext_ai_agent_workflow_run_steps'));
        $this->assertTrue(Schema::hasTable('ext_ai_agent_workflow_approvals'));
        $indexes = DB::select("PRAGMA index_list('ext_ai_agent_workflow_runs')");
        $indexNames = array_map(static fn (object $index): string => $index->name, $indexes);
        $this->assertContains('ext_ai_agent_run_workflow_idempotency_unique', $indexNames);
    }
}
