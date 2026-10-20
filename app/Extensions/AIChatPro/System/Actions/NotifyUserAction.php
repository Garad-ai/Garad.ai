<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;

/**
 * Final step of a Garad scheduled task. It has no external side effects: it records the message in the
 * run output so the result can be delivered into the owner's chat history.
 */
final class NotifyUserAction implements ActionInterface
{
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $message = trim((string) ($config['message'] ?? ''));
        $skipIf = trim((string) ($config['skip_if'] ?? ''));

        if ($message === '' || ($skipIf !== '' && $message === $skipIf)) {
            $context['notification_skipped'] = true;

            return $context;
        }

        $context['notification_title'] = (string) ($config['title'] ?? $workflow->name);
        $context['notification_message'] = $message;

        return $context;
    }
}
