<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Models\OpenaiGeneratorChatCategory;
use App\Models\UserOpenaiChat;
use App\Models\UserOpenaiChatMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Delivers finished Garad scheduled-task results into each owner's chat history.
 */
class GaradScheduledDeliveryService
{
    public function deliver(int $userId): int
    {
        $workflows = AIAgentWorkflow::query()
            ->where('user_id', $userId)
            ->where('trigger_config->garad_task', true)
            ->get(['id', 'name', 'trigger_config']);
        if ($workflows->isEmpty()) {
            return 0;
        }

        $task = app(GaradScheduledTaskService::class);
        $runQuery = AIAgentWorkflowRun::query()
            ->whereIn('workflow_id', $workflows->pluck('id'))
            ->where('trigger_data->garad_chat_runner', true)
            ->whereNull('trigger_data->chat_delivered')
            ->orderBy('id');

        $delivered = 0;
        $runQuery->chunkById(100, function ($runs) use ($workflows, $task, $userId, &$delivered): void {
        foreach ($runs as $run) {
            if (! in_array($run->status->value, ['completed', 'failed'], true)) {
                continue;
            }
            $nextAttempt = $run->trigger_data['next_delivery_attempt_at'] ?? null;
            if ($nextAttempt && Carbon::parse($nextAttempt)->isFuture()) {
                continue;
            }
            $workflow = $workflows->firstWhere('id', $run->workflow_id);
            $text = trim((string) $task->executionResult($run));
            $failed = $run->status->value === 'failed';
            if (! $failed && ($text === '' || $text === 'NO_UPDATE')) {
                $this->markDelivered($run, 0, 'no_result');

                continue;
            }
            if (($workflow?->trigger_config['notification_enabled'] ?? true) === false) {
                $this->markDelivered($run, 0, 'notification_disabled');

                continue;
            }

            $lock = Cache::lock('garad-sched-deliver:' . $run->id, 60);
            if (! $lock->get()) {
                continue;
            }
            try {
                if ($run->fresh()?->trigger_data['chat_delivered'] ?? null) {
                    continue;
                }
                $body = $failed
                    ? 'This scheduled task could not complete: ' . ($run->error_message ?: 'unknown error') . '. You can run it again from the Scheduled page.'
                    : $text;
                $this->createChat($userId, (string) $workflow?->name, $run, $body);
                $delivered++;
            } catch (Throwable $e) {
                $fresh = $run->fresh();
                if ($fresh) {
                    $data = (array) ($fresh->trigger_data ?? []);
                    $data['delivery_error'] = $e->getMessage();
                    $data['delivery_attempted_at'] = now()->toIso8601String();
                    $attempts = (int) ($data['delivery_attempts'] ?? 0) + 1;
                    $data['delivery_attempts'] = $attempts;
                    $retryMinutes = [1, 5, 15, 60][min($attempts - 1, 3)];
                    $data['next_delivery_attempt_at'] = now()->addMinutes($retryMinutes)->toIso8601String();
                    $fresh->trigger_data = $data;
                    $fresh->save();
                }
                Log::error('[GaradChat] Could not deliver scheduled result.', ['run' => $run->id, 'error' => $e->getMessage()]);
            } finally {
                $lock->release();
            }
        }
        });

        return $delivered;
    }

    public function deliverForAllUsers(): int
    {
        $userIds = AIAgentWorkflowRun::query()
            ->where('trigger_data->garad_chat_runner', true)
            ->whereNull('trigger_data->chat_delivered')
            ->whereIn('status', ['completed', 'failed'])
            ->whereHas('workflow', fn ($query) => $query->where('trigger_config->garad_task', true))
            ->distinct()
            ->pluck('workflow_id')
            ->map(fn ($workflowId) => AIAgentWorkflow::query()->whereKey($workflowId)->value('user_id'))
            ->filter()
            ->unique();

        $delivered = 0;
        foreach ($userIds as $userId) {
            $delivered += $this->deliver((int) $userId);
        }

        return $delivered;
    }

    private function createChat(int $userId, string $title, AIAgentWorkflowRun $run, string $body): int
    {
        return DB::transaction(function () use ($userId, $title, $run, $body): int {
            $category = OpenaiGeneratorChatCategory::query()->where('slug', 'like', '%ai-chat-bot%')->first()
                ?? OpenaiGeneratorChatCategory::query()->first();

            $chat = new UserOpenaiChat;
            $chat->user_id = $userId;
            $chat->openai_chat_category_id = $category?->id;
            $chat->chatbot_id = $category?->chatbot_id;
            $chat->title = Str::limit('⏰ ' . ($title !== '' ? $title : 'Scheduled task'), 80, '');
            $chat->total_credits = 0;
            $chat->total_words = str_word_count($body);
            $chat->save();

            $message = new UserOpenaiChatMessage;
            $message->user_openai_chat_id = $chat->id;
            $message->user_id = $userId;
            $message->input = 'Scheduled task: ' . ($title !== '' ? $title : 'Scheduled task')
                . ' (ran ' . ($run->completed_at ?? $run->created_at)?->toDayDateTimeString() . ')';
            $message->response = $body;
            $message->output = $body;
            $message->hash = Str::random(256);
            $message->credits = 0;
            $message->words = str_word_count($body);
            $message->save();

            $this->markDelivered($run, (int) $chat->id);

            return (int) $chat->id;
        });
    }

    private function markDelivered(
        AIAgentWorkflowRun $run,
        int $chatId,
        ?string $skipReason = null,
    ): void
    {
        $data = (array) ($run->trigger_data ?? []);
        $data['chat_delivered'] = $chatId > 0 ? $chatId : 'skipped';
        unset($data['delivery_error']);
        unset($data['next_delivery_attempt_at']);
        if ($chatId > 0) {
            $data['delivery_at'] = now()->toIso8601String();
            unset($data['delivery_skip_reason']);
        } else {
            unset($data['delivery_at']);
            if ($skipReason !== null) {
                $data['delivery_skip_reason'] = $skipReason;
            }
        }
        $run->trigger_data = $data;
        $run->save();
    }

}
