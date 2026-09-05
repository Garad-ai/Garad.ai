<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Actions\Gmail;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentGmail\System\Gmail\GmailClientFactory;
use Google\Service\Gmail\ModifyThreadRequest;

class RemoveLabelFromConversationAction implements ActionInterface
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    /**
     * Config keys:
     *   - thread_id       (string)       : Gmail thread ID (required)
     *   - label_ids       (array|string) : label IDs to remove from all messages in thread (required)
     *   - store_output_as (string)       : context key (default: conversation_label_result)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'conversation_label_result';

        $gmail    = $this->factory->make($workflow->user_id);
        $threadId = $config['thread_id'] ?? '';
        $labelIds = (array) ($config['label_ids'] ?? []);

        $request = new ModifyThreadRequest();
        $request->setRemoveLabelIds($labelIds);

        $gmail->users_threads->modify('me', $threadId, $request);

        return array_merge($context, [$storeOutputAs => ['thread_id' => $threadId, 'removed_labels' => $labelIds]]);
    }
}
