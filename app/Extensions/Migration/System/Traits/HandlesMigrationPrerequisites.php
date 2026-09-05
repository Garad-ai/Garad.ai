<?php

declare(strict_types=1);

namespace App\Extensions\Migration\System\Traits;

use App\Extensions\Migration\System\Enums\MigrationCapabilityEnum;
use InvalidArgumentException;

trait HandlesMigrationPrerequisites
{
    protected ?string $sqlFilePath = null;

    protected ?string $envFilePath = null;

    protected function collectPreReqs(array $options = []): void
    {
        $this->sqlFilePath = $options['sql_file'] ?? null;
        $this->envFilePath = $options['env_file'] ?? null;
    }

    protected function validateRequiredFiles(string $capability, array $required, array $options): void
    {
        foreach ($required as $reqEnum) {
            $key = $reqEnum->value;
            $path = $options[$key] ?? null;

            if (empty($path)) {
                $capLabel = MigrationCapabilityEnum::tryFrom($capability)?->label();

                throw new InvalidArgumentException(
                    "Missing required pre request {$reqEnum->type()} : {$reqEnum->label()} for {$capLabel} transfer"
                );
            }

            if (! is_string($path) || ! file_exists($path) || ! is_readable($path)) {
                throw new InvalidArgumentException(
                    "The file path for '{$reqEnum->label()}' is invalid or not readable: {$path}"
                );
            }
        }
    }
}
