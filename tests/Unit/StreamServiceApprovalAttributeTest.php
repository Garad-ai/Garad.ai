<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Stream\StreamService;
use Illuminate\Http\Request;
use ReflectionClass;
use Tests\TestCase;

final class StreamServiceApprovalAttributeTest extends TestCase
{
    public function test_agent_approval_attribute_is_read_and_removed_once(): void
    {
        $request = Request::create('/');
        $request->attributes->set('garad_agent_pending_approval_id', '42');
        $this->app->instance('request', $request);

        $serviceClass = new ReflectionClass(StreamService::class);
        $service = $serviceClass->newInstanceWithoutConstructor();
        $takeApprovalId = $serviceClass->getMethod('takeAgentPendingApprovalId');
        $takeApprovalId->setAccessible(true);

        self::assertSame('42', $takeApprovalId->invoke($service));
        self::assertNull($takeApprovalId->invoke($service));
    }
}
