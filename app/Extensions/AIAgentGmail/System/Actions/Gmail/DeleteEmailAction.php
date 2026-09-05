<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Actions\Gmail;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentGmail\System\Gmail\GmailClientFactory;

class DeleteEmailAction implements ActionInterface
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    /**
     * Config keys:
     *   - message_id      (string) : Gmail message ID to trash (required)
     *   - store_output_as (string) : context key (default: deleted_email)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'deleted_email';

        $gmail     = $this->factory->make($workflow->user_id);
        $messageId = $config['message_id'] ?? '';

        $gmail->users_messages->trash('me', $messageId);

        return array_merge($context, [$storeOutputAs => ['message_id' => $messageId, 'trashed' => true]]);
    }
}
