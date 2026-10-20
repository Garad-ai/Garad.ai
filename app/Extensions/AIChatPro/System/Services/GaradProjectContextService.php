<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatProFolders\System\Models\AiChatProFolder;
use App\Models\UserOpenaiChat;
use Illuminate\Support\Facades\Auth;

final class GaradProjectContextService
{
    public function forChat(?int $chatId): string
    {
        if (! Auth::check() || ! $chatId) {
            return '';
        }

        $chat = UserOpenaiChat::query()
            ->where('id', $chatId)
            ->where('user_id', Auth::id())
            ->first(['id', 'folder_id']);

        if (! $chat?->folder_id) {
            return '';
        }

        $project = AiChatProFolder::query()
            ->where('id', $chat->folder_id)
            ->where('created_by', Auth::id())
            ->first(['id', 'name', 'description', 'instructions']);

        if (! $project) {
            return '';
        }

        $parts = ["Active Garad Project: {$project->name}"];
        if (filled($project->description)) {
            $parts[] = 'Project description: ' . trim((string) $project->description);
        }
        if (filled($project->instructions)) {
            $parts[] = "Project instructions (follow these for this conversation):\n" . trim((string) $project->instructions);
        }

        return implode("\n", $parts);
    }
}
