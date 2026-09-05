<?php

namespace App\Extensions\Migration\System\Enums;

enum MigrationReqsEnum: string
{
    case SQL_FILE = 'sql_file';
    case ENV_FILE = 'env_file';

    public function label(): string
    {
        return match ($this) {
            self::SQL_FILE => 'SQL File',
            self::ENV_FILE => 'Environment File (.env)',
        };
    }

    public function type(): string
    {
        return match ($this) {
            self::SQL_FILE, self::ENV_FILE => 'file',
        };
    }
}
