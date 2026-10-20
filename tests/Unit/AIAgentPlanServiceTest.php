<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIAgent\System\Memory\MemoryRepository;
use App\Extensions\AIAgent\System\Services\AIAgentPlanService;
use App\Models\User;
use Mockery;
use Tests\TestCase;

final class AIAgentPlanServiceTest extends TestCase
{
    public function test_admins_bypass_agent_plan_limits(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isAdmin')->times(3)->andReturn(true);
        $user->shouldNotReceive('activePlan');

        $service = new AIAgentPlanService(Mockery::mock(MemoryRepository::class));

        $service->checkWorkflowLimit($user);
        $service->checkChannelLimit($user);
        $service->checkMemoryLimit($user);

        $this->assertTrue(true);
    }
}
