<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Engine\ActionDispatcher;
use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowRunStatusEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Http\Controllers\AgentOperationsController;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowApproval;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRunStep;
use App\Extensions\AIAgent\System\Services\AIAgentChatService;
use App\Extensions\AIAgent\System\Services\AIAgentPlanService;
use App\Extensions\AIAgent\System\Triggers\ChannelMessageTrigger;
use App\Extensions\AIAgent\System\Triggers\ScheduleTrigger;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AIAgentWorkflowApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default'                                    => 'sqlite',
            'database.connections.sqlite.database'                => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('ext_ai_agent_workflows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('status');
            $table->string('trigger_type');
            $table->json('trigger_config')->nullable();
            $table->json('actions')->nullable();
            $table->json('steps')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ext_ai_agent_workflow_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workflow_id')->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->string('status')->default('running');
            $table->json('trigger_data')->nullable();
            $table->json('steps_output')->nullable();
            $table->json('run_context')->nullable();
            $table->unsignedInteger('current_step_order')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ext_ai_agent_workflow_run_steps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workflow_run_id');
            $table->unsignedInteger('step_index');
            $table->string('step_id')->nullable();
            $table->string('type');
            $table->string('status');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['workflow_run_id', 'step_index']);
        });

        Schema::create('ext_ai_agent_workflow_approvals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('workflow_run_id');
            $table->unsignedBigInteger('workflow_run_step_id')->unique();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('decision_by')->nullable();
            $table->string('status');
            $table->json('payload')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_protected_action_is_persisted_and_paused_until_approval(): void
    {
        $workflow = $this->workflow();
        $action = Mockery::mock(AIAgentActionInterface::class);
        $action->shouldReceive('getConfigSchema')->twice()->andReturn([
            'required' => ['recipient_id', 'message'],
        ]);
        $action->shouldReceive('execute')->once()->andReturn(['delivered' => true]);

        $dispatcher = Mockery::mock(ActionDispatcher::class);
        $dispatcher->shouldReceive('resolve')->with('send_message')->times(3)->andReturn($action);
        $engine = new WorkflowEngine($dispatcher, new ScheduleTrigger, new ChannelMessageTrigger);

        $run = $engine->run($workflow);
        $this->assertSame(WorkflowRunStatusEnum::PendingApproval, $run->status);
        $this->assertSame('waiting_for_approval', $run->steps()->firstOrFail()->status);
        $this->assertSame('pending', $run->steps()->firstOrFail()->approval->status);
        $this->assertDatabaseHas('ext_ai_agent_workflow_approvals', [
            'workflow_run_id' => $run->id,
            'status'          => 'pending',
        ]);

        $run->steps()->firstOrFail()->approval->update([
            'status'      => 'approved',
            'decision_by' => 7,
            'decided_at'  => now(),
        ]);
        $completed = $engine->resumeAfterApproval($run->fresh());

        $this->assertSame(WorkflowRunStatusEnum::Completed, $completed->status);
        $this->assertSame('completed', $completed->steps()->firstOrFail()->status);
        $this->assertSame(['delivered' => true], $completed->steps()->firstOrFail()->output);
    }

    public function test_non_owner_cannot_start_a_workflow(): void
    {
        $workflow = $this->workflow();
        Auth::shouldReceive('id')->once()->andReturn(9001);

        try {
            (new AgentOperationsController)->run(
                Request::create('/operations/workflows/1/run', 'POST'),
                $workflow,
                Mockery::mock(WorkflowEngine::class),
            );
            $this->fail('A non-owner must not start another user\'s workflow.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_replaying_a_run_request_with_the_same_key_does_not_create_another_run(): void
    {
        $workflow = $this->workflow();
        $action = Mockery::mock(AIAgentActionInterface::class);
        $action->shouldReceive('getConfigSchema')->once()->andReturn([
            'required' => ['recipient_id', 'message'],
        ]);
        $action->shouldNotReceive('execute');

        $dispatcher = Mockery::mock(ActionDispatcher::class);
        $dispatcher->shouldReceive('resolve')->with('send_message')->once()->andReturn($action);
        $engine = new WorkflowEngine($dispatcher, new ScheduleTrigger, new ChannelMessageTrigger);

        $first = $engine->run($workflow, [], [], 'same-request-uuid');
        $replayed = $engine->run($workflow, [], [], 'same-request-uuid');

        $this->assertSame($first->id, $replayed->id);
        $this->assertSame(1, AIAgentWorkflowRun::query()->count());
        $this->assertSame(1, AIAgentWorkflowApproval::query()->count());
        $this->assertSame(WorkflowRunStatusEnum::PendingApproval, $replayed->status);
    }

    public function test_conversation_activity_is_owner_scoped_and_does_not_expose_step_payloads(): void
    {
        $ownerWorkflow = $this->workflow();
        $otherWorkflow = AIAgentWorkflow::query()->create([
            'user_id' => 8,
            'name' => 'Private workflow',
            'status' => WorkflowStatusEnum::Paused,
            'trigger_type' => TriggerTypeEnum::Webhook,
            'steps' => [],
        ]);
        $run = AIAgentWorkflowRun::query()->create([
            'workflow_id' => $ownerWorkflow->id,
            'status' => WorkflowRunStatusEnum::Running,
            'trigger_data' => ['chat_id' => 55, 'message_id' => 99, 'task' => 'Private request', 'trigger_type' => 'chat_pro'],
            'started_at' => now(),
        ]);
        AIAgentWorkflowRunStep::query()->create([
            'workflow_run_id' => $run->id,
            'step_index' => 0,
            'step_id' => 'step-message',
            'type' => 'send_message',
            'status' => 'running',
            'input' => ['secret' => 'must not appear'],
            'output' => ['private' => 'must not appear'],
            'started_at' => now(),
        ]);
        $approvalRun = AIAgentWorkflowRun::query()->create([
            'workflow_id' => $ownerWorkflow->id,
            'status' => WorkflowRunStatusEnum::PendingApproval,
            'trigger_data' => ['chat_id' => 55, 'message_id' => 98, 'trigger_type' => 'chat_pro'],
            'started_at' => now(),
        ]);
        $approvalStep = AIAgentWorkflowRunStep::query()->create([
            'workflow_run_id' => $approvalRun->id,
            'step_index' => 1,
            'step_id' => 'approval-step',
            'type' => 'send_message',
            'status' => 'waiting_for_approval',
            'started_at' => now(),
        ]);
        AIAgentWorkflowApproval::query()->create([
            'workflow_id' => $ownerWorkflow->id,
            'workflow_run_id' => $approvalRun->id,
            'workflow_run_step_id' => $approvalStep->id,
            'requested_by' => 7,
            'status' => 'pending',
        ]);
        AIAgentWorkflowRun::query()->create([
            'workflow_id' => $otherWorkflow->id,
            'status' => WorkflowRunStatusEnum::Running,
            'trigger_data' => ['chat_id' => 55, 'message_id' => 100, 'trigger_type' => 'chat_pro'],
            'started_at' => now(),
        ]);
        Auth::shouldReceive('id')->once()->andReturn(7);

        $response = (new AgentOperationsController)->conversationActivity(55);
        $data = $response->getData(true)['data'];

        $this->assertCount(2, $data);
        $runningPayload = collect($data)->firstWhere('id', (string) $run->id);
        $approvalPayload = collect($data)->firstWhere('id', (string) $approvalRun->id);
        $this->assertSame('99', $runningPayload['messageId']);
        $this->assertSame('Preparing a message', $runningPayload['steps'][0]['label']);
        $this->assertSame('In progress', $runningPayload['steps'][0]['summary']);
        $this->assertArrayNotHasKey('input', $runningPayload['steps'][0]);
        $this->assertArrayNotHasKey('output', $runningPayload['steps'][0]);
        $this->assertSame('pending_approval', $approvalPayload['status']);
        $this->assertSame('Waiting for your approval', $approvalPayload['steps'][0]['summary']);
        $this->assertSame((string) AIAgentWorkflowApproval::query()->where('workflow_run_id', $approvalRun->id)->value('id'), $approvalPayload['steps'][0]['approvalId']);
        $this->assertStringNotContainsString('must not appear', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function test_workflow_cancellation_stops_after_the_current_action_and_keeps_it_completed(): void
    {
        $workflow = $this->workflow();
        $workflow->update([
            'steps' => [[
                'id' => 'step-ai',
                'type' => 'ai_call',
                'action' => 'ai_call',
                'config' => [],
                'order' => 0,
            ]],
        ]);
        $action = Mockery::mock(AIAgentActionInterface::class);
        $action->shouldReceive('getConfigSchema')->andReturn(['required' => []]);
        $action->shouldReceive('execute')->once()->andReturnUsing(
            function (array $config, array $context) use ($workflow): array {
                AIAgentWorkflowRun::query()
                    ->where('workflow_id', $workflow->id)
                    ->update(['status' => WorkflowRunStatusEnum::Cancelling->value]);

                return ['delivered' => true];
            },
        );
        $dispatcher = Mockery::mock(ActionDispatcher::class);
        $dispatcher->shouldReceive('resolve')->with('ai_call')->andReturn($action);
        $run = (new WorkflowEngine($dispatcher, new ScheduleTrigger, new ChannelMessageTrigger))
            ->run($workflow);

        $this->assertSame(WorkflowRunStatusEnum::Cancelled, $run->status);
        $this->assertSame('completed', $run->steps()->firstOrFail()->status);
        $this->assertNotNull($run->completed_at);
    }

    public function test_cancelling_a_waiting_approval_cancels_the_run_and_approval_without_executing_it(): void
    {
        $workflow = $this->workflow();
        $run = AIAgentWorkflowRun::query()->create([
            'workflow_id' => $workflow->id,
            'status' => WorkflowRunStatusEnum::PendingApproval,
            'trigger_data' => ['chat_id' => 55, 'trigger_type' => 'chat_pro'],
            'started_at' => now(),
        ]);
        $step = AIAgentWorkflowRunStep::query()->create([
            'workflow_run_id' => $run->id,
            'step_index' => 0,
            'step_id' => 'step-message',
            'type' => 'send_message',
            'status' => 'waiting_for_approval',
            'started_at' => now(),
        ]);
        $approval = AIAgentWorkflowApproval::query()->create([
            'workflow_id' => $workflow->id,
            'workflow_run_id' => $run->id,
            'workflow_run_step_id' => $step->id,
            'requested_by' => 7,
            'status' => 'pending',
        ]);
        Auth::shouldReceive('id')->once()->andReturn(7);

        $response = (new AgentOperationsController)->cancelRun($run);

        $this->assertSame('cancelled', $response->getData(true)['data']['status']);
        $this->assertSame('cancelled', $step->fresh()->status);
        $this->assertSame('cancelled', $approval->fresh()->status);
    }

    public function test_non_owner_cannot_approve_a_protected_action(): void
    {
        $workflow = $this->workflow();
        $run = AIAgentWorkflowRun::query()->create([
            'workflow_id' => $workflow->id,
            'status'      => WorkflowRunStatusEnum::PendingApproval,
            'started_at'  => now(),
        ]);
        $step = AIAgentWorkflowRunStep::query()->create([
            'workflow_run_id' => $run->id,
            'step_index'      => 0,
            'type'            => 'send_message',
            'status'          => 'waiting_for_approval',
        ]);
        $approval = AIAgentWorkflowApproval::query()->create([
            'workflow_id'          => $workflow->id,
            'workflow_run_id'      => $run->id,
            'workflow_run_step_id' => $step->id,
            'requested_by'         => 7,
            'status'               => 'pending',
        ]);
        Auth::shouldReceive('id')->once()->andReturn(9001);

        try {
            (new AgentOperationsController)->decideApproval(
                Request::create('/operations/approvals/' . $approval->id . '/decision', 'POST', [
                    'decision' => 'approve',
                ]),
                $approval,
                Mockery::mock(WorkflowEngine::class),
            );
            $this->fail('A non-owner must not approve another user\'s action.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseHas('ext_ai_agent_workflow_approvals', [
            'id'          => $approval->id,
            'status'      => 'pending',
            'decision_by' => null,
        ]);
    }

    public function test_authenticated_api_persists_workflow_run_and_approval_results(): void
    {
        config(['app.status' => 'Production']);

        $user = (new User)->forceFill([
            'id'    => 7,
            'name'  => 'Workflow Owner',
            'email' => 'workflow-owner@example.test',
        ]);
        $user->exists = true;
        $this->actingAs($user);

        $chatService = Mockery::mock(AIAgentChatService::class);
        $chatService->shouldReceive('forUser')->once()->with($user)->andReturnSelf();
        $chatService->shouldReceive('chat')->once()->andReturn(json_encode([
            'name'           => 'Approved Message',
            'trigger_type'   => 'webhook',
            'trigger_config' => [],
            'steps'          => [[
                'type'   => 'send_message',
                'action' => 'send_message',
                'app'    => 'Channels',
                'config' => [
                    'recipient_id' => 'sandbox-recipient',
                    'message'      => 'Approved test content',
                ],
                'order' => 0,
            ]],
        ], JSON_THROW_ON_ERROR));
        $this->app->instance(AIAgentChatService::class, $chatService);

        $planService = Mockery::mock(AIAgentPlanService::class);
        $planService->shouldReceive('checkWorkflowLimit')->once()->with($user);
        $this->app->instance(AIAgentPlanService::class, $planService);

        $created = $this->postJson('/dashboard/user/ai-agent/workflows/create-from-prompt', [
            'prompt' => 'Send the prepared message after I approve it.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'paused');

        $workflowId = $created->json('data.id');

        $this->getJson('/dashboard/user/ai-agent/operations/workflows')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $workflowId)
            ->assertJsonPath('data.0.status', 'paused');

        $action = Mockery::mock(AIAgentActionInterface::class);
        $action->shouldReceive('getConfigSchema')->twice()->andReturn([
            'required' => ['recipient_id', 'message'],
        ]);
        $action->shouldReceive('execute')->once()->andReturn([
            'recipient_id'  => 'sandbox-recipient',
            'delivered'     => true,
        ]);

        $dispatcher = Mockery::mock(ActionDispatcher::class);
        $dispatcher->shouldReceive('resolve')->with('send_message')->times(3)->andReturn($action);
        $this->app->instance(ActionDispatcher::class, $dispatcher);

        $runResponse = $this->withHeader('Idempotency-Key', 'workflow-api-run-1')
            ->postJson("/dashboard/user/ai-agent/operations/workflows/{$workflowId}/run")
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.steps.0.status', 'waiting_for_approval');

        $approvalId = $runResponse->json('data.steps.0.approvalId');

        $this->getJson('/dashboard/user/ai-agent/operations/runs')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending_approval');
        $this->getJson('/dashboard/user/ai-agent/operations/approvals')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $approvalId)
            ->assertJsonPath('data.0.status', 'pending');

        $this->postJson("/dashboard/user/ai-agent/operations/approvals/{$approvalId}/decision", [
            'decision' => 'approve',
            'comment'  => 'Authorized in the test sandbox.',
        ])
            ->assertOk()
            ->assertJsonPath('decision', 'approved')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.steps.0.status', 'completed')
            ->assertJsonPath('data.steps.0.output.delivered', true);

        $this->assertDatabaseHas('ext_ai_agent_workflow_runs', [
            'workflow_id' => $workflowId,
            'status'      => WorkflowRunStatusEnum::Completed->value,
        ]);
        $this->assertDatabaseHas('ext_ai_agent_workflow_approvals', [
            'id'     => $approvalId,
            'status' => 'approved',
        ]);
    }

    private function workflow(): AIAgentWorkflow
    {
        return AIAgentWorkflow::query()->create([
            'user_id'      => 7,
            'name'         => 'Approved message',
            'status'       => WorkflowStatusEnum::Paused,
            'trigger_type' => TriggerTypeEnum::Webhook,
            'steps'        => [[
                'id'     => 'step-message',
                'type'   => 'send_message',
                'action' => 'send_message',
                'config' => [
                    'recipient_id' => 'sandbox-recipient',
                    'message'      => 'Approved test content',
                ],
                'order' => 0,
            ]],
        ]);
    }
}
