<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Support;

use Illuminate\Support\Str;

final class GaradToolResult
{
    private const PREFIX = '[[GARAD_TOOL:';

    private const SUFFIX = ']]';

    public static function encode(string $kind, array $payload = []): string
    {
        $payload = array_merge([
            'kind' => $kind,
            'id'   => (string) Str::uuid(),
        ], $payload);

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if (! is_string($json)) {
            $json = json_encode([
                'kind'    => 'error',
                'id'      => (string) Str::uuid(),
                'message' => 'Unable to encode Chat Pro result.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return self::PREFIX . base64_encode((string) $json) . self::SUFFIX;
    }

    public static function error(string $message): string
    {
        return self::encode('error', [
            'message' => trim($message),
        ]);
    }
}
