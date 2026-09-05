<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Engine;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;

class AIAgentActionRegistry
{
    /** @var array<string, class-string<ActionInterface>> */
    private array $actions = [];

    /** @var array<int, array{key: string, label: string}> */
    private array $failed = [];

    /**
     * Register an action class under a key.
     *
     * @param  class-string<ActionInterface>  $actionClass
     */
    public function register(string $key, string $actionClass): void
    {
        $this->actions[$key] = $actionClass;
    }

    /**
     * Resolve an action instance by key.
     *
     * @throws Exception
     */
    public function resolve(string $key): ActionInterface
    {
        if (! isset($this->actions[$key])) {
            throw new Exception("AIAgentActionRegistry: action [{$key}] is not registered.");
        }

        return app($this->actions[$key]);
    }

    /**
     * Return actions that failed to resolve due to missing dependencies.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function failed(): array
    {
        return $this->failed;
    }

    /**
     * Return all registered actions grouped by category.
     * Each entry includes metadata if the action implements AIAgentActionInterface.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function all(): array
    {
        $this->failed = [];
        $grouped = [];

        foreach ($this->actions as $key => $class) {
            try {
                $instance = app($class);
            } catch (BindingResolutionException) {
                $this->failed[] = [
                    'key'   => $key,
                    'label' => (string) str(class_basename($class))->replaceLast('Action', '')->headline(),
                ];

                continue;
            }

            if ($instance instanceof AIAgentActionInterface) {
                $category = $instance->getCategory();

                $grouped[$category][] = [
                    'key'           => $key,
                    'label'         => $instance->getLabel(),
                    'description'   => $instance->getDescription(),
                    'icon'          => $instance->getIcon(),
                    'category'      => $category,
                    'config_schema' => $instance->getConfigSchema(),
                ];
            } else {
                $grouped['utilities'][] = [
                    'key'           => $key,
                    'label'         => $key,
                    'description'   => '',
                    'icon'          => 'tabler-bolt',
                    'category'      => 'utilities',
                    'config_schema' => [],
                ];
            }
        }

        return $grouped;
    }

    /**
     * Return actions for a specific category.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byCategory(string $category): array
    {
        return $this->all()[$category] ?? [];
    }

    /**
     * Return a flat list of all registered action keys.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->actions);
    }
}
