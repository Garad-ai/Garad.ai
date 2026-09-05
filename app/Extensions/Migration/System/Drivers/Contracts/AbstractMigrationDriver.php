<?php

declare(strict_types=1);

namespace App\Extensions\Migration\System\Drivers\Contracts;

use App\Extensions\Migration\System\Traits\HandlesMigrationCapabilities;
use App\Extensions\Migration\System\Traits\HandlesMigrationPrerequisites;
use InvalidArgumentException;

abstract class AbstractMigrationDriver implements MigrationDriverInterface
{
    use HandlesMigrationCapabilities;
    use HandlesMigrationPrerequisites;

    protected static array $capabilityMap;

    public function getName(): string
    {
        return static::enum()->label();
    }

    public function migrate(array $options = []): mixed
    {
        $this->collectPreReqs($options);

        $capability = $options['capability'] ?? null;

        if ($capability === null) {
            return $this->migrateAll($options);
        }

        $config = $this->getCapabilityConfig($capability);

        $this->validateRequiredFiles($capability, $config['reqs'], $options);

        $method = $config['function'];

        if (! method_exists($this, $method)) {
            throw new InvalidArgumentException("Migration method '{$method}' not implemented for '{$capability}' transfer");
        }

        return $this->{$method}($options);
    }

    protected function migrateAll(array $options): array
    {
        $results = [];

        foreach (static::$capabilityMap as $capability => $config) {
            $this->validateRequiredFiles($capability, $config['reqs'], $options);
            $results[$capability] = $this->{$config['function']}($options);
        }

        return $results;
    }
}
