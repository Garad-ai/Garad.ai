<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Models\User;
use App\Notifications\LiveNotification;
use Exception;

final class NotifyUserAction implements AIAgentActionInterface
{
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $user = $workflow->user_id ? User::query()->find($workflow->user_id) : null;
        if (! $user) {
            throw new Exception('NotifyUserAction: workflow user could not be resolved.');
        }

        $title = trim((string) ($config['title'] ?? 'Garad AI'));
        $message = trim((string) ($config['message'] ?? ''));
        $skipIf = trim((string) ($config['skip_if'] ?? ''));

        if ($message === '') {
            throw new Exception('NotifyUserAction: message is required.');
        }

        if ($skipIf !== '' && mb_strtoupper($message) === mb_strtoupper($skipIf)) {
            return array_merge($context, ['notification_skipped' => true]);
        }

        $link = route('dashboard.user.openai.chat.pro.scheduled.index', ['run' => $run->id]);
        $user->notifyNow(new LiveNotification($message, $link, $title));

        return array_merge($context, [
            'notification_sent' => true,
            'notification_title' => $title,
            'notification_message' => $message,
        ]);
    }

    public function getCategory(): string { return 'garad'; }
    public function getLabel(): string { return 'Notify User'; }
    public function getDescription(): string { return 'Send a first-party Garad notification to the workflow owner.'; }
    public function getIcon(): string { return 'tabler-bell'; }
    public function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'message' => ['type' => 'string'],
                'skip_if' => ['type' => 'string'],
            ],
            'required' => ['message'],
        ];
    }
}
