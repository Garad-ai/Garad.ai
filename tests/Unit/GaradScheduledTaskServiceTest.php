<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowRunStatusEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgent\System\Triggers\ScheduleTrigger;
use App\Extensions\AIChatPro\System\Services\GaradScheduledDeliveryService;
use App\Extensions\AIChatPro\System\Services\GaradScheduledTaskService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class GaradScheduledTaskServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_failed_one_time_run_stays_failed_and_is_not_scheduled_again(): void
    {
        $workflow = $this->workflow([
            'run_once_at' => now()->subMinute()->toIso8601String(),
        ], now()->subMinute());
        $this->mockRun(WorkflowRunStatusEnum::Failed, 'Provider timed out.');

        $task = app(GaradScheduledTaskService::class)->runNow((int) $workflow->id, 'one-time-failure');
        $this->assertSame('failed', $task['status']);
        $this->assertNull($task['next_run_at']);
        $this->assertNotNull($task['last_run_at']);
        $this->assertSame('failed', $task['history'][0]['status']);
        $this->assertSame('Provider timed out.', $task['history'][0]['error']);
        $this->assertSame('pending', $task['history'][0]['delivery_status']);
    }

    public function test_manual_run_of_overdue_recurring_task_advances_next_run_even_on_failure(): void
    {
        $workflow = $this->workflow([
            'cron' => '0 8 * * *',
            'timezone' => 'Africa/Mogadishu',
        ], now()->subDay());
        $this->mockRun(WorkflowRunStatusEnum::Failed, 'Temporary provider outage.');

        $task = app(GaradScheduledTaskService::class)->runNow((int) $workflow->id, 'recurring-failure');
        $next = Carbon::parse($task['next_run_at']);
        $this->assertTrue($next->isFuture());
        $this->assertSame('failed', $task['status']);
        $this->assertSame('Africa/Mogadishu', $task['timezone']);
    }

    public function test_recurring_schedule_fires_when_next_run_equals_now(): void
    {
        $workflow = $this->workflow([
            'cron' => '0 8 * * *',
            'timezone' => 'UTC',
        ], now()->copy());

        $this->assertTrue(app(ScheduleTrigger::class)->shouldFire($workflow->fresh()));
    }

    public function test_successful_manual_run_preserves_an_already_future_recurring_occurrence(): void
    {
        $scheduledNext = now()->addHour()->startOfMinute();
        $workflow = $this->workflow([
            'cron' => '0 8 * * *',
            'timezone' => 'Africa/Mogadishu',
        ], $scheduledNext);
        $this->mockRun(WorkflowRunStatusEnum::Completed);

        $task = app(GaradScheduledTaskService::class)->runNow((int) $workflow->id, 'recurring-success');

        $this->assertSame(
            $scheduledNext->toIso8601String(),
            Carbon::parse($task['next_run_at'])->toIso8601String(),
        );
        $this->assertSame('completed', $task['history'][0]['status']);
    }

    public function test_editing_a_completed_one_time_task_does_not_reopen_or_erase_its_run(): void
    {
        $scheduledAt = now()->subDay()->toIso8601String();
        $workflow = $this->workflow([
            'run_once_at' => $scheduledAt,
        ], now()->subDay());
        $workflow->update([
            'last_run_at' => now()->subHour(),
            'next_run_at' => null,
        ]);
        $this->completedRun($workflow, ['task_result' => 'Previously completed report']);

        $task = app(GaradScheduledTaskService::class)->update((int) $workflow->id, [
            'title' => 'Updated title',
            'instructions' => 'Updated instructions',
        ]);

        $this->assertSame('Updated title', $task['title']);
        $this->assertSame('Updated instructions', $task['instructions']);
        $this->assertSame($scheduledAt, $task['run_at']);
        $this->assertNull($task['next_run_at']);
        $this->assertSame('completed', $task['status']);
        $this->assertNotNull($task['last_run_at']);
    }

    public function test_rescheduling_a_completed_one_time_task_arms_a_new_occurrence(): void
    {
        $workflow = $this->workflow([
            'run_once_at' => now()->subDay()->toIso8601String(),
        ], now()->subDay());
        $workflow->update([
            'last_run_at' => now()->subHour(),
            'next_run_at' => null,
        ]);
        $this->completedRun($workflow, ['task_result' => 'Previously completed report']);
        $newRunAt = now()->addDay()->setTimezone('Africa/Mogadishu');

        $task = app(GaradScheduledTaskService::class)->update((int) $workflow->id, [
            'schedule_type' => 'one_time',
            'run_at' => $newRunAt->format('Y-m-d H:i:s'),
            'timezone' => 'Africa/Mogadishu',
        ]);

        $this->assertNull($task['last_run_at']);
        $this->assertTrue(Carbon::parse($task['next_run_at'])->isFuture());
        $this->assertSame('active', $task['status']);
    }

    public function test_disabled_chat_notification_keeps_the_result_in_history_without_delivery(): void
    {
        $workflow = $this->workflow([
            'cron' => '0 8 * * *',
            'notification_enabled' => false,
        ], now()->addDay());
        $run = $this->completedRun($workflow, ['task_result' => 'Saved report']);

        $delivered = app(GaradScheduledDeliveryService::class)->deliver((int) $this->user->id);

        $run->refresh();
        $this->assertSame(0, $delivered);
        $this->assertSame('skipped', $run->trigger_data['chat_delivered']);
        $this->assertSame('notification_disabled', $run->trigger_data['delivery_skip_reason']);
        $this->assertSame(
            'skipped',
            app(GaradScheduledTaskService::class)->serialize($workflow->fresh())['history'][0]['delivery_status'],
        );
    }

    public function test_delivery_processes_pending_runs_beyond_a_single_page(): void
    {
        $workflow = $this->workflow([
            'cron' => '0 8 * * *',
            'notification_enabled' => false,
        ], now()->addDay());
        for ($index = 0; $index < 30; $index++) {
            $this->completedRun($workflow, ['task_result' => 'Saved report ' . $index]);
        }

        $this->assertSame(
            0,
            app(GaradScheduledDeliveryService::class)->deliver((int) $this->user->id),
        );
        $this->assertSame(
            0,
            AIAgentWorkflowRun::query()
                ->where('workflow_id', $workflow->id)
                ->whereNull('trigger_data->chat_delivered')
                ->count(),
        );
    }

    public function test_failed_chat_delivery_is_left_pending_with_a_backoff(): void
    {
        $workflow = $this->workflow([
            'cron' => '0 8 * * *',
            'notification_enabled' => true,
        ], now()->addDay());
        $run = $this->completedRun($workflow, ['task_result' => 'Saved report']);
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new RuntimeException('Chat storage unavailable.'));

        $delivery = app(GaradScheduledDeliveryService::class);
        $this->assertSame(0, $delivery->deliver((int) $this->user->id));

        $run->refresh();
        $this->assertSame('Chat storage unavailable.', $run->trigger_data['delivery_error']);
        $this->assertSame(1, $run->trigger_data['delivery_attempts']);
        $this->assertTrue(Carbon::parse($run->trigger_data['next_delivery_attempt_at'])->isFuture());
        $this->assertSame(
            'failed',
            app(GaradScheduledTaskService::class)->serialize($workflow->fresh())['history'][0]['delivery_status'],
        );
        $this->assertSame(0, $delivery->deliver((int) $this->user->id));
    }

    private function workflow(array $config, Carbon $nextRunAt): AIAgentWorkflow
    {
        return AIAgentWorkflow::query()->create([
            'user_id' => $this->user->id,
            'name' => 'Test scheduled workflow',
            'description' => 'Isolated test fixture',
            'status' => WorkflowStatusEnum::Active,
            'trigger_type' => TriggerTypeEnum::Schedule,
            'trigger_config' => array_merge([
                'garad_task' => true,
                'garad_task_type' => 'agent_task',
                'timezone' => 'UTC',
                'instructions' => 'Produce a deterministic fixture result.',
                'notification_enabled' => true,
                'created_from' => 'test',
            ], $config),
            'steps' => [],
            'next_run_at' => $nextRunAt,
        ]);
    }

    private function completedRun(AIAgentWorkflow $workflow, array $output): AIAgentWorkflowRun
    {
        return AIAgentWorkflowRun::query()->create([
            'workflow_id' => $workflow->id,
            'status' => WorkflowRunStatusEnum::Completed,
            'trigger_data' => ['garad_chat_runner' => true],
            'steps_output' => [['output' => $output]],
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
        ]);
    }

    private function mockRun(WorkflowRunStatusEnum $status, ?string $error = null): void
    {
        $engine = Mockery::mock(WorkflowEngine::class);
        $engine->shouldReceive('run')->once()->andReturnUsing(
            function (AIAgentWorkflow $workflow, array $triggerData) use ($status, $error): AIAgentWorkflowRun {
                return AIAgentWorkflowRun::query()->create([
                    'workflow_id' => $workflow->id,
                    'status' => $status,
                    'trigger_data' => $triggerData,
                    'steps_output' => $status === WorkflowRunStatusEnum::Completed
                        ? [['output' => ['task_result' => 'Deterministic fixture result.']]]
                        : [],
                    'error_message' => $error,
                    'started_at' => now()->subSecond(),
                    'completed_at' => now(),
                ]);
            },
        );
        $this->app->instance(WorkflowEngine::class, $engine);
    }
}
