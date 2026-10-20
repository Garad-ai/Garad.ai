<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIChatPro\System\Http\Controllers\ScheduledTaskController;
use App\Extensions\AIChatPro\System\Services\GaradScheduledDeliveryService;
use App\Extensions\AIChatPro\System\Services\GaradScheduledTaskService;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

final class ScheduledTaskControllerTest extends TestCase
{
    public function test_browser_tick_delivers_results_without_starting_workflows(): void
    {
        Auth::shouldReceive('id')->once()->andReturn(42);

        $delivery = Mockery::mock(GaradScheduledDeliveryService::class);
        $delivery->shouldReceive('deliver')->once()->with(42)->andReturn(0);
        $delivery->shouldNotReceive('spawnIfDue');

        $controller = new ScheduledTaskController(
            new GaradScheduledTaskService(),
        );

        $response = $controller->tick($delivery);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'delivered' => 0,
            'started' => false,
        ], $response->getData(true));
    }
}
