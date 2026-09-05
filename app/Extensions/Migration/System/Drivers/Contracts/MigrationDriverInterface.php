<?php

namespace App\Extensions\Migration\System\Drivers\Contracts;

use App\Extensions\Migration\System\Enums\MigrationDriverEnum;

interface MigrationDriverInterface
{
    public static function enum(): MigrationDriverEnum;

    public function getName(): string;

    public function migrate(array $options = []): mixed;

    public function supportedCapabilities(array $options = []): array;
}
