<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Actions\Gmail;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentGmail\System\Gmail\GmailClientFactory;
use Google\Service\Gmail\Label;

class CreateLabelAction implements ActionInterface
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    /**
     * Config keys:
     *   - name                    (string) : label name (required)
     *   - label_list_visibility   (string) : labelShow|labelShowIfUnread|labelHide (default: labelShow)
     *   - message_list_visibility (string) : show|hide (default: show)
     *   - store_output_as         (string) : context key (default: created_label)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'created_label';

        $gmail = $this->factory->make($workflow->user_id);

        $label = new Label();
        $label->setName($config['name'] ?? '');
        $label->setLabelListVisibility($config['label_list_visibility'] ?? 'labelShow');
        $label->setMessageListVisibility($config['message_list_visibility'] ?? 'show');

        $created = $gmail->users_labels->create('me', $label);

        return array_merge($context, [
            $storeOutputAs => [
                'label_id' => $created->getId(),
                'name'     => $created->getName(),
            ],
        ]);
    }
}
