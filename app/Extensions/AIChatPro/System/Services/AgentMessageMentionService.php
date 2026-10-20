<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIChatPro\System\Models\AgentMessageMention;
use App\Models\User;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgentMessageMentionService
{
    /**
     * @param array<int, int|string> $recipientIds
     * @return Collection<int, User>
     */
    public function validateRecipients(
        User $sender,
        UserOpenaiChat $chat,
        string $content,
        array $recipientIds,
    ): Collection {
        if ($recipientIds === []) {
            return collect();
        }

        if (! Schema::hasTable('ext_ai_chat_pro_agent_conversations')) {
            throw ValidationException::withMessages([
                'mentionUserIds' => 'Agent mentions are not available until the collaboration migration has been applied.',
            ]);
        }

        $workspaceId = $this->workspaceId($chat);
        $recipients = User::query()->whereIn('id', $recipientIds)->get()->keyBy('id');
        $eligible = collect();

        foreach (array_unique(array_map('intval', $recipientIds)) as $recipientId) {
            /** @var User|null $recipient */
            $recipient = $recipients->get($recipientId);
            if (! $recipient || $recipient->id === $sender->id) {
                throw ValidationException::withMessages([
                    'mentionUserIds' => 'One or more selected mention recipients are unavailable.',
                ]);
            }

            if (! $recipient->canViewProfile($sender) || ! $recipient->allow_mentions) {
                throw ValidationException::withMessages([
                    'mentionUserIds' => 'One or more selected users are not eligible to receive mentions.',
                ]);
            }

            if ($workspaceId !== null && (
                ! $sender->hasWorkspaceRole('team', $workspaceId)
                || ! $recipient->hasWorkspaceRole('team', $workspaceId)
            )) {
                throw ValidationException::withMessages([
                    'mentionUserIds' => 'Mentions in this workspace are limited to active workspace members.',
                ]);
            }

            $username = (string) $recipient->public_username;
            $messageHasMention = $username !== '' && preg_match(
                '/(?<![A-Za-z0-9._-])@' . preg_quote($username, '/') . '(?=$|[\s,!?;:)\]}]|\.(?=$|[\s,!?;:)\]}]))/iu',
                $content,
            ) === 1;
            if (! $messageHasMention) {
                throw ValidationException::withMessages([
                    'mentionUserIds' => 'The message must contain each selected public username.',
                ]);
            }

            $eligible->push($recipient);
        }

        return $eligible;
    }

    /**
     * @param Collection<int, User> $recipients
     */
    public function persist(
        User $sender,
        UserOpenaiChat $chat,
        UserOpenaiChatMessage $message,
        Collection $recipients,
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($sender, $chat, $message, $recipients): void {
            foreach ($recipients as $recipient) {
                $reference = AgentMessageMention::query()->firstOrCreate(
                    [
                        'message_id'   => $message->id,
                        'recipient_id' => $recipient->id,
                    ],
                    [
                        'chat_id'            => $chat->id,
                        'sender_id'          => $sender->id,
                        'recipient_username' => $recipient->public_username,
                    ],
                );

                if (! $reference->wasRecentlyCreated) {
                    continue;
                }

                $recipient->notifications()->create([
                    'id'   => (string) Str::uuid(),
                    'type' => 'agent_social',
                    'data' => [
                        'type'          => 'mention',
                        'title'         => '@' . $sender->public_username . ' mentioned you',
                        'detail'        => 'You were mentioned in an Agent message. This notification does not grant access to its conversation.',
                        'actorId'       => (string) $sender->id,
                        'actorUsername' => $sender->public_username,
                        'messageId'     => (string) $message->id,
                    ],
                ]);
            }
        });
    }

    private function workspaceId(UserOpenaiChat $chat): ?int
    {
        if (! Schema::hasColumn($chat->getTable(), 'team_id') || ! $chat->team_id) {
            return null;
        }

        return (int) $chat->team_id;
    }
}
