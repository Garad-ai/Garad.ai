<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Memory;

use Illuminate\Support\Collection;

class MemoryInjector
{
    /**
     * Convert memory entries into a formatted system prompt block.
     *
     * @param  Collection<int, \App\Extensions\AIAgent\System\Models\AIAgentMemory>  $memories
     */
    public function toSystemPrompt(Collection $memories): string
    {
        if ($memories->isEmpty()) {
            return '';
        }

        $lines = ['## User Memory', ''];

        foreach ($memories as $entry) {
            $lines[] = "- {$entry->memory}";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Prepend the memory block to an existing system prompt.
     *
     * @param  Collection<int, \App\Extensions\AIAgent\System\Models\AIAgentMemory>  $memories
     */
    public function prepend(string $systemPrompt, Collection $memories): string
    {
        $memoryBlock = $this->toSystemPrompt($memories);

        if ($memoryBlock === '') {
            return $systemPrompt;
        }

        return $memoryBlock . "\n---\n\n" . $systemPrompt;
    }
}
