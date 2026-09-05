<?php

declare(strict_types=1);

namespace App\Extensions\Migration\System\Services;

use App\Extensions\Migration\System\Drivers\Contracts\MigrationDriverInterface;
use App\Extensions\Migration\System\Enums\MigrationDriverEnum;

class MigrationService
{
    /** @var MigrationDriverInterface[] */
    protected array $providers = [];

    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    public function getAvailableProviders(): array
    {
        return $this->providers;
    }

    public function migrate(MigrationDriverEnum $driverEnum, array $options = []): mixed
    {
        return app($driverEnum->driver())->migrate($options);
    }

    public function migrateAllProviders(array $options = []): array
    {
        $results = [];

        foreach ($this->providers as $provider) {
            $results[$provider::enum()->value] = $provider->migrate($options);
        }

        return $results;
    }
}
