<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Actions\Gmail;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentGmail\System\Gmail\GmailClientFactory;
use Google\Service\Gmail\ModifyMessageRequest;

class RemoveLabelFromEmailAction implements ActionInterface
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    /**
     * Config keys:
     *   - message_id      (string)       : Gmail message ID (required)
     *   - label_ids       (array|string) : label IDs to remove (required)
     *   - store_output_as (string)       : context key (default: label_result)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'label_result';

        $gmail     = $this->factory->make($workflow->user_id);
        $messageId = $config['message_id'] ?? '';
        $labelIds  = (array) ($config['label_ids'] ?? []);

        $request = new ModifyMessageRequest();
        $request->setRemoveLabelIds($labelIds);

        $gmail->users_messages->modify('me', $messageId, $request);

        return array_merge($context, [$storeOutputAs => ['message_id' => $messageId, 'removed_labels' => $labelIds]]);
    }
}
