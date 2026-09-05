<?php

declare(strict_types=1);

namespace App\Extensions\Migration\System\Traits;

use InvalidArgumentException;

trait HandlesMigrationCapabilities
{
    public function supportedCapabilities(array $options = []): array
    {
        return array_combine(
            array_keys(static::$capabilityMap),
            array_map(
                static fn ($capability) => $capability['enum']?->label(),
                static::$capabilityMap
            )
        );
    }

    protected function getCapabilityConfig(string $capability): array
    {
        $config = static::$capabilityMap[$capability] ?? null;

        if (! $config) {
            throw new InvalidArgumentException("Unsupported capability: {$capability}");
        }

        return $config;
    }
}
