<?php

declare(strict_types=1);

namespace App\Extensions\Migration\System\Utils;

use RuntimeException;

class SqlSchemaParser
{
    public static function extractTableColumns(string $sql, string $tableName): array
    {
        $start = strpos($sql, "CREATE TABLE `{$tableName}` (");
        if ($start === false) {
            throw new RuntimeException("CREATE TABLE `{$tableName}` not found.");
        }

        $sqlAfterCreate = substr($sql, $start);
        $end = strpos($sqlAfterCreate, ') ENGINE=');
        if ($end === false) {
            throw new RuntimeException("Could not find end of CREATE TABLE `{$tableName}` block.");
        }

        $tableDef = substr($sqlAfterCreate, 0, $end);
        $lines = explode("\n", $tableDef);

        $columns = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '`')) {
                $parts = explode('`', $line);
                if (isset($parts[1])) {
                    $columns[] = $parts[1];
                }
            }
        }

        return $columns;
    }
}
