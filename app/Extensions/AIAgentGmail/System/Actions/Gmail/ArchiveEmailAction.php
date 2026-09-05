<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Actions\Gmail;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentGmail\System\Gmail\GmailClientFactory;
use Google\Service\Gmail\ModifyMessageRequest;

class ArchiveEmailAction implements ActionInterface
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    /**
     * Config keys:
     *   - message_id      (string) : Gmail message ID (required)
     *   - store_output_as (string) : context key (default: archived_email)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'archived_email';

        $gmail     = $this->factory->make($workflow->user_id);
        $messageId = $config['message_id'] ?? '';

        $request = new ModifyMessageRequest();
        $request->setRemoveLabelIds(['INBOX']);

        $gmail->users_messages->modify('me', $messageId, $request);

        return array_merge($context, [$storeOutputAs => ['message_id' => $messageId, 'archived' => true]]);
    }
}
