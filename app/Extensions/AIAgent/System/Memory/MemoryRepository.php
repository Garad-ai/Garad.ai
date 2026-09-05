<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Memory;

use App\Extensions\AIAgent\System\Models\AIAgentMemory;
use Illuminate\Support\Collection;

class MemoryRepository
{
    /**
     * Load all memory entries for a user.
     *
     * @return Collection<int, AIAgentMemory>
     */
    public function forUser(int $userId): Collection
    {
        return AIAgentMemory::query()
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    /**
     * Load specific memory entries by their IDs.
     *
     * @param  int[]  $ids
     *
     * @return Collection<int, AIAgentMemory>
     */
    public function findByIds(int $userId, array $ids): Collection
    {
        return AIAgentMemory::query()
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * Create a new memory entry.
     */
    public function remember(int $userId, string $memory): AIAgentMemory
    {
        return AIAgentMemory::query()->create([
            'user_id' => $userId,
            'memory'  => $memory,
        ]);
    }

    /**
     * Update an existing memory entry.
     */
    public function update(AIAgentMemory $memory, string $content): AIAgentMemory
    {
        $memory->update(['memory' => $content]);

        return $memory;
    }

    /**
     * Delete a single memory entry by model.
     */
    public function forget(AIAgentMemory $memory): void
    {
        $memory->delete();
    }

    /**
     * Delete all memory for a user (used on uninstall / account deletion).
     */
    public function forgetAll(int $userId): void
    {
        AIAgentMemory::query()->where('user_id', $userId)->delete();
    }

    public function countForUser(int $userId): int
    {
        return AIAgentMemory::query()->where('user_id', $userId)->count();
    }
}
