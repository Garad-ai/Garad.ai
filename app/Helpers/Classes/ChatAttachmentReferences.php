<?php

namespace App\Helpers\Classes;

use Illuminate\Support\Arr;

final class ChatAttachmentReferences
{
    /**
     * @return array<int, string>
     */
    public static function parse(mixed $references): array
    {
        $items = is_array($references)
            ? Arr::flatten($references)
            : (is_string($references)
                ? preg_split('/,\s*(?=(?:https?:\/\/|\/?uploads\/|\/?dashboard\/user\/ai-chat-pro\/attachments\/))/i', trim($references)) ?: []
                : []);

        return array_values(array_filter(array_map(static function ($reference): string {
            return is_string($reference) ? trim($reference) : '';
        }, $items), static fn (string $reference): bool => $reference !== ''));
    }

    public static function normalize(mixed $references): ?string
    {
        if (is_string($references)) {
            $references = trim($references);

            return $references !== '' ? $references : null;
        }

        if (! is_array($references)) {
            return null;
        }

        $normalized = [];
        foreach (Arr::flatten($references) as $reference) {
            if (! is_string($reference)) {
                continue;
            }

            $reference = trim($reference);
            if ($reference !== '') {
                $normalized[] = $reference;
            }
        }

        return $normalized === [] ? null : implode(',', $normalized);
    }
}
