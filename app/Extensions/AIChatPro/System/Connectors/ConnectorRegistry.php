<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Connectors;

/**
 * Runtime registry of provider connector extensions.
 *
 * Bound as a singleton by AIChatProServiceProvider. Each provider extension
 * registers itself in its own boot() method, guarded by class_exists() so the
 * host extension stays optional.
 */
class ConnectorRegistry
{
    /** @var array<string, class-string<ConnectorDefinition>> */
    private array $providers = [];

    public function register(string $key, string $definitionClass): void
    {
        $this->providers[$key] = $definitionClass;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function get(string $key): ?ConnectorDefinition
    {
        $class = $this->providers[$key] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * @return array<string, ConnectorDefinition>
     */
    public function all(): array
    {
        $resolved = [];

        foreach ($this->providers as $key => $class) {
            $resolved[$key] = app($class);
        }

        return $resolved;
    }

    /**
     * @return array<string, ConnectorDefinition>
     */
    public function enabled(): array
    {
        return array_filter($this->all(), fn (ConnectorDefinition $definition): bool => $definition->isEnabled());
    }

    /**
     * Find the definition for a given tool function name by asking each provider.
     */
    public function findByFunctionName(string $functionName): ?ConnectorDefinition
    {
        foreach ($this->all() as $definition) {
            if (str_starts_with($functionName, 'connector_' . $definition->key() . '_')) {
                return $definition;
            }
        }

        return null;
    }
}
