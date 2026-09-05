<?php

declare(strict_types=1);

namespace App\Extensions\Migration\System\Utils;

use Illuminate\Support\Str;

class SqlValueParser
{
    public static function parseRow(string $row): array
    {
        $values = [];
        $current = '';
        $inString = false;
        $length = strlen($row);

        for ($i = 0; $i < $length; $i++) {
            $char = $row[$i];

            if ($char === "'" && ($i === 0 || $row[$i - 1] !== '\\')) {
                if ($inString && ($i + 1 < $length && $row[$i + 1] === "'")) {
                    // Escaped single quote
                    $current .= "'";
                    $i++;
                } else {
                    $inString = ! $inString;
                }

                continue;
            }

            if (! $inString && $char === ',') {
                $values[] = self::cleanValue($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $values[] = self::cleanValue($current);
        }

        return $values;
    }

    /**
     * Clean an individual value.
     */
    protected static function cleanValue(string $value): ?string
    {
        $value = trim($value);
        if (strtoupper($value) === 'NULL') {
            return null;
        }

        return Str::startsWith($value, "'") && Str::endsWith($value, "'")
            ? str_replace("''", "'", substr($value, 1, -1))
            : $value;
    }
}
