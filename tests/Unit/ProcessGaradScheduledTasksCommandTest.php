<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIChatPro\System\Services\GaradScheduledDeliveryService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class ProcessGaradScheduledTasksCommandTest extends TestCase
{
    public function test_command_is_registered_and_not_in_the_global_schedule(): void
    {
        $this->assertArrayHasKey('garad-chat:process-scheduled-tasks', \Illuminate\Support\Facades\Artisan::all());

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event) => (string) $event->command);

        $this->assertFalse($events->contains(fn ($command) => str_contains($command, 'garad-chat:process-scheduled-tasks')));
    }

    public function test_scope_restricts_processing_to_garad_tasks_and_requested_user(): void
    {
        $captured = null;
        $triggerData = null;
        $engine = Mockery::mock(WorkflowEngine::class);
        $engine->shouldReceive('processScheduledWorkflows')
            ->once()
            ->andReturnUsing(function ($command, $scope, $data) use (&$captured, &$triggerData) {
                $query = AIAgentWorkflow::query();
                $scope($query);
                $captured = [$query->toSql(), $query->getBindings()];
                $triggerData = $data;

                return 0;
            });
        $this->app->instance(WorkflowEngine::class, $engine);
        $delivery = Mockery::mock(GaradScheduledDeliveryService::class);
        $delivery->shouldReceive('deliver')->once()->with(42)->andReturn(0);
        $this->app->instance(GaradScheduledDeliveryService::class, $delivery);

        Log::spy();
        $this->artisan('garad-chat:process-scheduled-tasks', ['--user' => '42'])
            ->expectsOutputToContain('No Garad scheduled tasks were due.')
            ->assertSuccessful();

        Log::shouldHaveReceived('info')
            ->with('[GaradChat] No Garad scheduled tasks were due.', ['user_id' => 42]);
        $this->assertNotNull($captured);
        $this->assertStringContainsString('garad_task', $captured[0]);
        $this->assertStringContainsString('user_id', $captured[0]);
        $this->assertStringContainsString('next_run_at', $captured[0]);
        $this->assertContains(42, $captured[1]);
        $this->assertSame(['garad_chat_runner' => true], $triggerData);
    }

    public function test_non_numeric_user_option_is_rejected_without_processing(): void
    {
        $engine = Mockery::mock(WorkflowEngine::class);
        $engine->shouldNotReceive('processScheduledWorkflows');
        $this->app->instance(WorkflowEngine::class, $engine);

        $this->artisan('garad-chat:process-scheduled-tasks', ['--user' => 'abc'])
            ->assertExitCode(2);
    }

    public function test_unscoped_command_processes_only_garad_tasks_and_delivers_for_all_users(): void
    {
        $captured = null;
        $engine = Mockery::mock(WorkflowEngine::class);
        $engine->shouldReceive('processScheduledWorkflows')
            ->once()
            ->andReturnUsing(function ($command, $scope, $data) use (&$captured) {
                $query = AIAgentWorkflow::query();
                $scope($query);
                $captured = [$query->toSql(), $query->getBindings(), $data];

                return 0;
            });
        $this->app->instance(WorkflowEngine::class, $engine);
        $delivery = Mockery::mock(GaradScheduledDeliveryService::class);
        $delivery->shouldReceive('deliverForAllUsers')->once()->andReturn(0);
        $this->app->instance(GaradScheduledDeliveryService::class, $delivery);

        $this->artisan('garad-chat:process-scheduled-tasks')
            ->expectsOutputToContain('Delivered 0 result(s) to chat.')
            ->assertSuccessful();

        $this->assertNotNull($captured);
        $this->assertStringContainsString('garad_task', $captured[0]);
        $this->assertStringContainsString('next_run_at', $captured[0]);
        $this->assertStringNotContainsString('user_id', $captured[0]);
        $this->assertSame(['garad_chat_runner' => true], $captured[2]);
    }
}
