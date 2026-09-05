<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Engine;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use Exception;

class ActionDispatcher
{
    public function __construct(private readonly AIAgentActionRegistry $registry) {}

    /**
     * Resolve an action handler by type key string.
     *
     * @throws Exception
     */
    public function resolve(string $type): ActionInterface
    {
        return $this->registry->resolve($type);
    }
}
